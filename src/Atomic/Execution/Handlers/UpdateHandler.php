<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Atomic\Execution\Handlers;

use AlexFigures\JsonApi\Atomic\Execution\OperationOutcome;
use AlexFigures\JsonApi\Atomic\Lid\LidRegistry;
use AlexFigures\JsonApi\Atomic\Operation;
use AlexFigures\JsonApi\Contract\Data\ResourceProcessor;
use AlexFigures\JsonApi\Http\Error\ErrorMapper;
use AlexFigures\JsonApi\Http\Exception\BadRequestException;
use AlexFigures\JsonApi\Http\Write\ChangeSetFactory;
use AlexFigures\JsonApi\Resource\Registry\ResourceRegistryInterface;
use Stringable;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/** @internal */
final readonly class UpdateHandler
{
    public function __construct(
        private ResourceProcessor $processor,
        private ChangeSetFactory $changeSet,
        private ResourceRegistryInterface $registry,
        private PropertyAccessorInterface $accessor,
        private ErrorMapper $errors,
        private ?\AlexFigures\JsonApi\Http\Write\InputDocumentValidator $inputValidator = null,
    ) {
    }

    public function handle(Operation $operation, LidRegistry $lids): OperationOutcome
    {
        $data = $operation->data;
        if (!is_array($data)) {
            throw new BadRequestException('The "data" member MUST be present for update operations.', [
                $this->errors->invalidPointer($operation->pointer . '/data', 'Update operations MUST include a resource object.'),
            ]);
        }

        $type = $operation->ref?->type;
        if ($type === null && isset($data['type']) && is_string($data['type']) && $data['type'] !== '') {
            $type = $data['type'];
        }

        if ($type === null) {
            throw new BadRequestException('Unable to resolve resource type for update operation.');
        }

        /** @var array<string, mixed> $data */
        $id = $this->resolveIdentifier($operation, $data, $lids);
        if (isset($data['id']) && $data['id'] !== $id) {
            throw new BadRequestException('Identifier mismatch.', [$this->errors->invalidPointer('/data/id', 'The data identifier must match the target.')]);
        }

        $attributes = $data['attributes'] ?? null;
        $attributes ??= [];

        if ($attributes instanceof \stdClass) {
            $attributes = [];
        }
        if (!is_array($attributes)) {
            throw new BadRequestException('Resource attributes must be an object.');
        }

        /** @var array<string, mixed> $attributes */

        $validator = $this->inputValidator ?? new \AlexFigures\JsonApi\Http\Write\InputDocumentValidator(
            $this->registry,
            new \AlexFigures\JsonApi\Http\Write\WriteConfig(true),
            new \AlexFigures\JsonApi\Http\Error\ErrorMapper(new \AlexFigures\JsonApi\Http\Error\ErrorBuilder(true))
        );
        $validated = $validator->validateAndExtract($type, null, ['data' => $data], 'ATOMIC_UPDATE', true);
        $attributes = $validated['attributes'];
        $relationships = $this->extractRelationships($data, $lids);

        $changes = $this->changeSet->fromInput($type, $attributes, $relationships);
        $model = $this->processor->processUpdate($type, $id, $changes);

        $metadata = $this->registry->getByType($type);
        $idProperty = $metadata->idPropertyPath ?? 'id';
        $idValue = $this->accessor->getValue($model, $idProperty);

        if (!is_scalar($idValue) && !($idValue instanceof Stringable)) {
            throw new BadRequestException('Unable to resolve resource identifier for persisted model.');
        }

        $resolvedId = (string) $idValue;

        return OperationOutcome::forResource($type, $resolvedId, $model);
    }

    /**
     * Extract relationships from operation data and resolve LIDs to actual IDs.
     *
     * @param  array<string, mixed>              $data
     * @return array<string, array{data: mixed}>
     */
    private function extractRelationships(array $data, LidRegistry $lids): array
    {
        if (!array_key_exists('relationships', $data) || $data['relationships'] instanceof \stdClass) {
            return [];
        }
        if (!is_array($data['relationships']) || array_is_list($data['relationships'])) {
            throw new BadRequestException('Relationships must be an object.');
        }

        $relationships = [];
        foreach ($data['relationships'] as $relName => $relData) {
            if (!is_array($relData) || !array_key_exists('data', $relData)) {
                throw new BadRequestException('A relationship must contain a data member.');
            }

            $relationships[$relName] = [
                'data' => $this->resolveLidsInRelationshipData($relData['data'], $lids),
            ];
        }

        return $relationships;
    }

    /**
     * Resolve LIDs in relationship data (to-one or to-many).
     *
     * @param  mixed $data
     * @return mixed
     */
    private function resolveLidsInRelationshipData(mixed $data, LidRegistry $lids): mixed
    {
        if ($data === null) {
            return null;
        }

        // To-one relationship: single resource identifier
        if (is_array($data) && isset($data['type'])) {
            return $this->resolveLidInIdentifier($data, $lids);
        }

        // To-many relationship: array of resource identifiers
        if (is_array($data) && array_is_list($data)) {
            return array_map(
                fn ($identifier) => $this->resolveLidInIdentifier($identifier, $lids),
                $data
            );
        }

        return $data;
    }

    /**
     * Resolve LID in a single resource identifier.
     *
     * @param  array<array-key, mixed> $identifier
     * @return array<array-key, mixed>
     */
    private function resolveLidInIdentifier(array $identifier, LidRegistry $lids): array
    {
        if (isset($identifier['id'], $identifier['lid'])) {
            throw new BadRequestException('Identifiers cannot contain both id and lid.');
        }
        if (isset($identifier['lid']) && is_string($identifier['lid']) && $lids->has($identifier['lid']) && $lids->getType($identifier['lid']) !== ($identifier['type'] ?? null)) {
            throw new BadRequestException('Local identifier type mismatch.');
        }
        // If identifier has 'lid' instead of 'id', resolve it
        if (isset($identifier['lid']) && is_string($identifier['lid'])) {
            $resolvedId = $lids->resolveId($identifier['lid']);
            if ($resolvedId === null) {
                throw new BadRequestException(
                    sprintf('Unknown local identifier "%s" in relationship.', $identifier['lid'])
                );
            }

            // Replace lid with resolved id
            $identifier['id'] = $resolvedId;
            unset($identifier['lid']);
        }

        return $identifier;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolveIdentifier(Operation $operation, array $data, LidRegistry $lids): string
    {
        if ($operation->ref?->id !== null) {
            return $operation->ref->id;
        }

        if ($operation->ref?->lid !== null) {
            $id = $lids->resolveId($operation->ref->lid);
            if ($id === null) {
                throw new BadRequestException('Unknown local identifier.', [
                    $this->errors->invalidPointer($operation->pointer . '/ref/lid', sprintf('Local identifier "%s" is not registered.', $operation->ref->lid)),
                ]);
            }

            return $id;
        }

        if (isset($data['id']) && is_string($data['id']) && $data['id'] !== '') {
            return $data['id'];
        }

        if (isset($data['lid']) && is_string($data['lid']) && $data['lid'] !== '') {
            $id = $lids->resolveId($data['lid']);
            if ($id === null) {
                throw new BadRequestException('Unknown local identifier.', [
                    $this->errors->invalidPointer($operation->pointer . '/data/lid', sprintf('Local identifier "%s" is not registered.', $data['lid'])),
                ]);
            }

            return $id;
        }

        throw new BadRequestException('Missing resource identifier.', [
            $this->errors->invalidPointer($operation->pointer . '/ref', 'Update operations MUST specify a resource identifier.'),
        ]);
    }
}
