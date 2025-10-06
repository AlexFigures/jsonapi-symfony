<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Atomic\Validation;

use JsonApi\Symfony\Atomic\AtomicConfig;
use JsonApi\Symfony\Atomic\Lid\LidRegistry;
use JsonApi\Symfony\Atomic\Operation;
use JsonApi\Symfony\Atomic\Ref;
use JsonApi\Symfony\Http\Error\ErrorMapper;
use JsonApi\Symfony\Http\Exception\BadRequestException;
use JsonApi\Symfony\Resource\Metadata\RelationshipMetadata;
use JsonApi\Symfony\Resource\Metadata\ResourceMetadata;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;

final class AtomicValidator
{
    private const ALLOWED_OPS = ['add', 'update', 'remove'];

    public function __construct(
        private readonly AtomicConfig $config,
        private readonly ResourceRegistryInterface $registry,
        private readonly ErrorMapper $errors,
    ) {
    }

    /**
     * @param list<Operation> $operations
     *
     * @return array{0: list<Operation>, 1: LidRegistry}
     */
    public function validate(array $operations): array
    {
        $lidRegistry = new LidRegistry();
        $validated = [];

        foreach ($operations as $operation) {
            $validated[] = $this->validateOperation($operation, $lidRegistry);
        }

        return [$validated, $lidRegistry];
    }

    private function validateOperation(Operation $operation, LidRegistry $lids): Operation
    {
        if (!in_array($operation->op, self::ALLOWED_OPS, true)) {
            throw new BadRequestException('Unsupported atomic operation.', [
                $this->errors->invalidPointer($operation->pointer . '/op', sprintf('Unknown operation "%s".', $operation->op)),
            ]);
        }

        if ($operation->ref !== null && $operation->href !== null) {
            throw new BadRequestException('Invalid target specification.', [
                $this->errors->invalidPointer($operation->pointer, 'Operations MUST specify either "ref" or "href", but not both.'),
            ]);
        }

        $ref = $operation->ref;
        if ($ref === null) {
            if ($operation->href === null) {
                throw new BadRequestException('Missing operation target.', [
                    $this->errors->invalidPointer($operation->pointer, 'Each operation MUST include a "ref" or "href" member.'),
                ]);
            }

            if (!$this->config->allowHref) {
                throw new BadRequestException('Href targets are disabled.', [
                    $this->errors->invalidPointer($operation->pointer . '/href', 'The "href" member is not allowed by server configuration.'),
                ]);
            }

            $ref = $this->refFromHref($operation->href, $operation->pointer . '/href');
        }

        $typePointer = $operation->ref !== null ? $operation->pointer . '/ref/type' : $operation->pointer . '/href';

        if (!$this->registry->hasType($ref->type)) {
            throw new BadRequestException('Unknown resource type.', [
                $this->errors->invalidPointer(
                    $typePointer,
                    sprintf('Resource type "%s" is not recognised.', $ref->type)
                ),
            ]);
        }

        $metadata = $this->registry->getByType($ref->type);

        if ($ref->relationship !== null) {
            $relationship = $metadata->relationships[$ref->relationship] ?? null;
            if ($relationship === null) {
                throw new BadRequestException('Unknown relationship.', [
                    $this->errors->invalidPointer($operation->pointer . '/ref/relationship', sprintf('Relationship "%s" is not defined for resource "%s".', $ref->relationship, $ref->type)),
                ]);
            }

            $this->validateRelationshipData($operation, $relationship, $lids);

            return new Operation($operation->op, $ref, $operation->href, $operation->data, $operation->meta, $operation->pointer);
        }

        $this->validateResourceTarget($operation, $ref, $metadata, $lids);

        return new Operation($operation->op, $ref, $operation->href, $operation->data, $operation->meta, $operation->pointer);
    }

    private function validateResourceTarget(Operation $operation, Ref $ref, ResourceMetadata $metadata, LidRegistry $lids): void
    {
        if ($operation->op === 'add') {
            $this->assertDataIsResource($operation);
            $data = $operation->data;
            \assert(is_array($data));
            $dataType = $data['type'] ?? null;
            if (!is_string($dataType) || $dataType === '') {
                throw new BadRequestException('Missing resource type.', [
                    $this->errors->invalidPointer($operation->pointer . '/data/type', 'Resource objects MUST specify a type.'),
                ]);
            }

            if ($dataType !== $ref->type) {
                throw new BadRequestException('Type mismatch.', [
                    $this->errors->invalidPointer($operation->pointer . '/data/type', sprintf('Resource type must be "%s", got "%s".', $ref->type, $dataType)),
                ]);
            }

            if (isset($data['lid']) && is_string($data['lid']) && $data['lid'] !== '') {
                $lids->register($data['lid'], $ref->type);
            }

            return;
        }

        if (!$ref->hasIdentifier()) {
            throw new BadRequestException('Missing identifier.', [
                $this->errors->invalidPointer($operation->pointer . '/ref', 'Resource operations other than "add" MUST specify an identifier.'),
            ]);
        }

        if ($operation->op === 'update') {
            $this->assertDataIsResource($operation);
            $data = $operation->data;
            \assert(is_array($data));
            $dataType = $data['type'] ?? null;
            if ($dataType !== null && (!is_string($dataType) || $dataType === '')) {
                throw new BadRequestException('Invalid resource type.', [
                    $this->errors->invalidPointer($operation->pointer . '/data/type', 'When present, the "type" member MUST be a non-empty string.'),
                ]);
            }

            if (is_string($dataType) && $dataType !== $ref->type) {
                throw new BadRequestException('Type mismatch.', [
                    $this->errors->invalidPointer($operation->pointer . '/data/type', sprintf('Resource type must be "%s", got "%s".', $ref->type, $dataType)),
                ]);
            }
        }
    }

    private function assertDataIsResource(Operation $operation): void
    {
        if (!is_array($operation->data) || array_is_list($operation->data)) {
            throw new BadRequestException('Resource data must be an object.', [
                $this->errors->invalidPointer($operation->pointer . '/data', 'The "data" member MUST be a resource object.'),
            ]);
        }
    }

    private function validateRelationshipData(Operation $operation, RelationshipMetadata $relationship, LidRegistry $lids): void
    {
        $targetType = $relationship->targetType;
        if ($targetType === null) {
            throw new BadRequestException('Relationship target type is not configured.', [
                $this->errors->invalidPointer($operation->pointer . '/ref/relationship', sprintf('Relationship "%s" is missing a target type.', $relationship->name)),
            ]);
        }

        if ($relationship->toMany) {
            if ($operation->op === 'remove' || $operation->op === 'add' || $operation->op === 'update') {
                if (!is_array($operation->data) || !array_is_list($operation->data)) {
                    throw new BadRequestException('Relationship data must be a list.', [
                        $this->errors->invalidPointer($operation->pointer . '/data', 'Relationship data for to-many relationships MUST be an array of resource identifiers.'),
                    ]);
                }

                foreach ($operation->data as $index => $identifier) {
                    $this->validateResourceIdentifier($operation, $identifier, $targetType, sprintf('%s/data/%d', $operation->pointer, $index), $lids);
                }

                return;
            }
        } else {
            if ($operation->op !== 'update') {
                throw new BadRequestException('Invalid operation for to-one relationship.', [
                    $this->errors->invalidPointer($operation->pointer . '/op', 'Only the "update" operation is allowed for to-one relationships.'),
                ]);
            }

            if ($operation->data === null) {
                return;
            }

            $this->validateResourceIdentifier($operation, $operation->data, $targetType, $operation->pointer . '/data', $lids);
        }
    }

    private function validateResourceIdentifier(Operation $operation, mixed $identifier, string $expectedType, string $pointer, LidRegistry $lids): void
    {
        if (!is_array($identifier) || array_is_list($identifier)) {
            throw new BadRequestException('Invalid resource identifier.', [
                $this->errors->invalidPointer($pointer, 'Resource identifiers MUST be objects containing at least a type member.'),
            ]);
        }

        $type = $identifier['type'] ?? null;
        if (!is_string($type) || $type === '') {
            throw new BadRequestException('Invalid resource identifier type.', [
                $this->errors->invalidPointer($pointer . '/type', 'Resource identifiers MUST contain a non-empty type.'),
            ]);
        }

        if ($type !== $expectedType) {
            throw new BadRequestException('Type mismatch.', [
                $this->errors->invalidPointer($pointer . '/type', sprintf('Resource type must be "%s", got "%s".', $expectedType, $type)),
            ]);
        }

        if (!isset($identifier['id']) && !isset($identifier['lid'])) {
            throw new BadRequestException('Missing identifier id.', [
                $this->errors->invalidPointer($pointer, 'Resource identifiers MUST include an "id" or "lid" member.'),
            ]);
        }

        if (isset($identifier['lid']) && is_string($identifier['lid']) && $identifier['lid'] !== '') {
            $lids->register($identifier['lid'], $type);
        }
    }

    private function refFromHref(string $href, string $pointer): Ref
    {
        if (str_contains($href, '://')) {
            throw new BadRequestException('Absolute href not allowed.', [
                $this->errors->invalidPointer($pointer, 'The "href" member MUST be a relative URI.'),
            ]);
        }

        if (!str_starts_with($href, '/')) {
            throw new BadRequestException('Invalid href.', [
                $this->errors->invalidPointer($pointer, 'The "href" member MUST start with the configured route prefix.'),
            ]);
        }

        $routePrefix = rtrim($this->config->routePrefix, '/');
        if ($routePrefix === '') {
            $routePrefix = '/';
        }

        if ($routePrefix !== '/' && !str_starts_with($href, $routePrefix . '/')) {
            throw new BadRequestException('Invalid href.', [
                $this->errors->invalidPointer($pointer, sprintf('The "href" member MUST start with "%s".', $routePrefix)),
            ]);
        }

        $path = $routePrefix === '/' ? substr($href, 1) : substr($href, strlen($routePrefix) + 1);
        $segments = $path === '' ? [] : explode('/', $path);

        if ($segments === []) {
            throw new BadRequestException('Invalid href.', [
                $this->errors->invalidPointer($pointer, 'Unable to resolve type from href.'),
            ]);
        }

        $type = array_shift($segments);
        $id = null;
        $relationship = null;

        if ($segments !== []) {
            $id = array_shift($segments) ?: null;
        }

        if ($segments !== [] && $segments[0] === 'relationships') {
            array_shift($segments);
            $relationship = array_shift($segments) ?: null;
        }

        return new Ref($type, $id, null, $relationship);
    }
}
