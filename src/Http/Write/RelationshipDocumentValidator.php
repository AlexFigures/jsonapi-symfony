<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Write;

use JsonApi\Symfony\Contract\Data\ExistenceChecker;
use JsonApi\Symfony\Http\Exception\BadRequestException;
use JsonApi\Symfony\Http\Exception\ConflictException;
use JsonApi\Symfony\Http\Exception\NotFoundException;
use JsonApi\Symfony\Resource\Metadata\RelationshipMetadata;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

final class RelationshipDocumentValidator
{
    public function __construct(
        private readonly ResourceRegistryInterface $registry,
        private readonly ExistenceChecker $exists,
    ) {
    }

    /**
     * @param array<int|string, mixed>|null $payload
     *
     * @return array{kind: 'to-one'|'to-many', data: null|array{type: string, id: string}|list<array{type: string, id: string}>}
     */
    public function validate(string $routeType, string $routeId, string $relationship, ?array $payload, string $method): array
    {
        if (!$this->registry->hasType($routeType)) {
            throw new NotFoundException(sprintf('Resource type "%s" not found.', $routeType));
        }

        if (!$this->exists->exists($routeType, $routeId)) {
            throw new NotFoundException(sprintf('Resource "%s" with id "%s" was not found.', $routeType, $routeId));
        }

        $metadata = $this->registry->getByType($routeType);
        $relationshipMetadata = $metadata->relationships[$relationship] ?? null;

        if (!$relationshipMetadata instanceof RelationshipMetadata) {
            throw new NotFoundException(sprintf('Relationship "%s" not found on resource "%s".', $relationship, $routeType));
        }

        $kind = $relationshipMetadata->toMany ? 'to-many' : 'to-one';

        if ($payload === null) {
            throw new BadRequestException('Request body must not be empty.');
        }

        if (!array_key_exists('data', $payload)) {
            throw new BadRequestException('Document must contain a "data" member.');
        }

        if ($kind === 'to-one') {
            if ($method === 'POST' || $method === 'DELETE') {
                throw new MethodNotAllowedHttpException(['PATCH'], 'Only PATCH is allowed for to-one relationship updates.');
            }

            return [
                'kind' => $kind,
                'data' => $this->validateToOneData($relationshipMetadata, $payload['data']),
            ];
        }

        return [
            'kind' => $kind,
            'data' => $this->validateToManyData($relationshipMetadata, $payload['data'], $method),
        ];
    }

    /**
     * @param mixed $data
     *
     * @return array{type: string, id: string}|null
     */
    private function validateToOneData(RelationshipMetadata $metadata, mixed $data): ?array
    {
        if ($data === null) {
            if (!$metadata->nullable) {
                throw new ConflictException(sprintf('Relationship "%s" cannot be set to null.', $metadata->name));
            }

            return null;
        }

        if (!is_array($data) || array_is_list($data)) {
            throw new BadRequestException('Relationship data must be an object.');
        }

        $type = $data['type'] ?? null;
        $id = $data['id'] ?? null;

        if (!is_string($type) || $type === '') {
            throw new BadRequestException('Relationship type must be a non-empty string.');
        }

        if (!is_string($id) || $id === '') {
            throw new BadRequestException('Relationship id must be a non-empty string.');
        }

        if ($metadata->targetType !== null && $metadata->targetType !== $type) {
            throw new ConflictException(sprintf('Relationship "%s" must reference resources of type "%s".', $metadata->name, $metadata->targetType));
        }

        if (!$this->exists->exists($type, $id)) {
            throw new NotFoundException(sprintf('Related resource "%s" with id "%s" was not found.', $type, $id));
        }

        return ['type' => $type, 'id' => $id];
    }

    /**
     * @param mixed $data
     *
     * @return list<array{type: string, id: string}>
     */
    private function validateToManyData(RelationshipMetadata $metadata, mixed $data, string $method): array
    {
        $allowed = ['PATCH', 'POST', 'DELETE'];

        if (!in_array($method, $allowed, true)) {
            throw new MethodNotAllowedHttpException($allowed, sprintf('Method %s is not allowed for relationship "%s".', $method, $metadata->name));
        }

        if (!is_array($data) || !array_is_list($data)) {
            throw new BadRequestException('Relationship data must be an array of resource identifier objects.');
        }

        $unique = [];
        $seen = [];

        foreach ($data as $index => $item) {
            if (!is_array($item) || array_is_list($item)) {
                throw new BadRequestException(sprintf('Relationship data entry at index %d must be an object.', $index));
            }

            $type = $item['type'] ?? null;
            $id = $item['id'] ?? null;

            if (!is_string($type) || $type === '') {
                throw new BadRequestException(sprintf('Relationship data entry at index %d must contain a non-empty "type".', $index));
            }

            if (!is_string($id) || $id === '') {
                throw new BadRequestException(sprintf('Relationship data entry at index %d must contain a non-empty "id".', $index));
            }

            if ($metadata->targetType !== null && $metadata->targetType !== $type) {
                throw new ConflictException(sprintf('Relationship "%s" must reference resources of type "%s".', $metadata->name, $metadata->targetType));
            }

            if (!$this->exists->exists($type, $id)) {
                throw new NotFoundException(sprintf('Related resource "%s" with id "%s" was not found.', $type, $id));
            }

            $key = $type . ':' . $id;
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = ['type' => $type, 'id' => $id];
        }

        return $unique;
    }
}
