<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Atomic\Execution\Handlers;

use AlexFigures\JsonApi\Atomic\Execution\OperationOutcome;
use AlexFigures\JsonApi\Atomic\Lid\LidRegistry;
use AlexFigures\JsonApi\Atomic\Operation;
use AlexFigures\JsonApi\Contract\Data\ResourceProcessor;
use AlexFigures\JsonApi\Http\Exception\BadRequestException;
use AlexFigures\JsonApi\Http\Write\ChangeSetFactory;
use AlexFigures\JsonApi\Resource\Registry\ResourceRegistryInterface;
use Stringable;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/** @internal */
final readonly class AddHandler
{
    public function __construct(
        private ResourceProcessor $processor,
        private ChangeSetFactory $changeSet,
        private ResourceRegistryInterface $registry,
        private PropertyAccessorInterface $accessor,
        private ?\AlexFigures\JsonApi\Bridge\Doctrine\Flush\FlushManager $flushManager = null,
        private ?\AlexFigures\JsonApi\Http\Write\WriteConfig $writeConfig = null,
        private ?\AlexFigures\JsonApi\Http\Write\InputDocumentValidator $inputValidator = null,
    ) {
    }

    public function handle(Operation $operation, LidRegistry $lids): OperationOutcome
    {
        $data = $operation->data;
        if (!is_array($data)) {
            throw new BadRequestException('The "data" member MUST be present for add operations.');
        }

        $type = $operation->ref?->type;
        if ($type === null && isset($data['type']) && is_string($data['type']) && $data['type'] !== '') {
            $type = $data['type'];
        }

        if ($type === null) {
            throw new BadRequestException('Unable to resolve resource type for add operation.');
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
            $this->writeConfig ?? new \AlexFigures\JsonApi\Http\Write\WriteConfig(true),
            new \AlexFigures\JsonApi\Http\Error\ErrorMapper(new \AlexFigures\JsonApi\Http\Error\ErrorBuilder(true))
        );
        $validated = $validator->validateAndExtract($type, null, ['data' => $data], 'POST', true);
        $attributes = $validated['attributes'];
        $relationships = $this->extractRelationships($data, $lids);

        $changes = $this->changeSet->fromInput($type, $attributes, $relationships);

        $clientId = null;
        if (isset($data['id']) && is_string($data['id']) && $data['id'] !== '') {
            $clientId = $data['id'];
        }

        if ($clientId !== null && $this->writeConfig !== null && !$this->writeConfig->allowClientId($type)) {
            throw new \AlexFigures\JsonApi\Http\Exception\ForbiddenException('Client-generated IDs are not allowed.');
        }
        $model = $this->processor->processCreate($type, $changes, $clientId);
        $this->flushManager?->flush();

        $metadata = $this->registry->getByType($type);
        $idProperty = $metadata->idPropertyPath ?? 'id';
        $idValue = $this->accessor->getValue($model, $idProperty);

        if (!is_scalar($idValue) && !($idValue instanceof Stringable)) {
            throw new BadRequestException('Unable to resolve resource identifier for persisted model.');
        }

        $id = (string) $idValue;

        if (isset($data['lid']) && is_string($data['lid']) && $data['lid'] !== '') {
            $lids->associate($data['lid'], $type, $id);
        }

        return OperationOutcome::forResource($type, $id, $model);
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
}
