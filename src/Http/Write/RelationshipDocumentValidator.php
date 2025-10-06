<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Write;

use JsonApi\Symfony\Contract\Data\ExistenceChecker;
use JsonApi\Symfony\Http\Error\ErrorCodes;
use JsonApi\Symfony\Http\Error\ErrorMapper;
use JsonApi\Symfony\Http\Exception\BadRequestException;
use JsonApi\Symfony\Http\Exception\ConflictException;
use JsonApi\Symfony\Http\Exception\MethodNotAllowedException;
use JsonApi\Symfony\Http\Exception\MultiErrorException;
use JsonApi\Symfony\Http\Exception\NotFoundException;
use JsonApi\Symfony\Resource\Metadata\RelationshipMetadata;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;

final class RelationshipDocumentValidator
{
    public function __construct(
        private readonly ResourceRegistryInterface $registry,
        private readonly ExistenceChecker $exists,
        private readonly ErrorMapper $errors,
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
            throw new NotFoundException('Resource type not found.', [$this->errors->unknownType($routeType)]);
        }

        if (!$this->exists->exists($routeType, $routeId)) {
            throw new NotFoundException('Resource not found.', [$this->errors->notFound(sprintf('Resource "%s" with id "%s" was not found.', $routeType, $routeId))]);
        }

        $metadata = $this->registry->getByType($routeType);
        $relationshipMetadata = $metadata->relationships[$relationship] ?? null;

        if (!$relationshipMetadata instanceof RelationshipMetadata) {
            throw new NotFoundException('Relationship not found.', [$this->errors->unknownRelationship($routeType, $relationship)]);
        }

        $expectedType = $relationshipMetadata->targetType;
        if ($expectedType === null && $relationshipMetadata->targetClass !== null) {
            $targetMetadata = $this->registry->getByClass($relationshipMetadata->targetClass);
            $expectedType = $targetMetadata?->type;
        }

        $kind = $relationshipMetadata->toMany ? 'to-many' : 'to-one';
        $pointerBase = '/data';
        $dataPointer = $pointerBase;

        if ($payload === null) {
            throw new BadRequestException('Request body must not be empty.', [$this->errors->invalidPointer('/', 'Request body must not be empty.')]);
        }

        if (!array_key_exists('data', $payload)) {
            throw new BadRequestException('Document must contain a "data" member.', [$this->errors->invalidPointer('/data', 'Document must contain a "data" member.')]);
        }

        if ($kind === 'to-one') {
            if ($method === 'POST' || $method === 'DELETE') {
                throw new MethodNotAllowedException(['PATCH'], 'Only PATCH is allowed for to-one relationship updates.', [$this->errors->methodNotAllowed(['PATCH'])]);
            }

            return [
                'kind' => $kind,
                'data' => $this->validateToOneData($relationshipMetadata, $expectedType, $payload['data'], $dataPointer),
            ];
        }

        return [
            'kind' => $kind,
            'data' => $this->validateToManyData($relationshipMetadata, $expectedType, $payload['data'], $dataPointer, $method),
        ];
    }

    /**
     * @param mixed $data
     *
     * @return array{type: string, id: string}|null
     */
    private function validateToOneData(RelationshipMetadata $metadata, ?string $expectedType, mixed $data, string $pointer): ?array
    {
        if ($data === null) {
            if (!$metadata->nullable) {
                throw new ConflictException('Relationship cannot be null.', [$this->errors->conflict(sprintf('Relationship "%s" cannot be set to null.', $metadata->name), $pointer)]);
            }

            return null;
        }

        if (!is_array($data) || array_is_list($data)) {
            throw new BadRequestException('Relationship data must be an object.', [$this->errors->invalidPointer($pointer, 'Relationship data must be an object.')]);
        }

        $type = $data['type'] ?? null;
        $id = $data['id'] ?? null;

        if (!is_string($type) || $type === '') {
            throw new BadRequestException('Relationship type must be a non-empty string.', [$this->errors->invalidPointer($pointer . '/type', 'Relationship type must be a non-empty string.')]);
        }

        if (!is_string($id) || $id === '') {
            throw new BadRequestException('Relationship id must be a non-empty string.', [$this->errors->invalidPointer($pointer . '/id', 'Relationship id must be a non-empty string.')]);
        }

        if ($expectedType !== null && $expectedType !== $type) {
            throw new ConflictException('Relationship type mismatch.', [$this->errors->invalidPointer($pointer . '/type', sprintf('Relationship "%s" must reference resources of type "%s".', $metadata->name, $expectedType), '409', ErrorCodes::TYPE_MISMATCH)]);
        }

        if (!$this->exists->exists($type, $id)) {
            throw new NotFoundException('Related resource not found.', [$this->errors->notFound(sprintf('Related resource "%s" with id "%s" was not found.', $type, $id), $pointer)]);
        }

        return ['type' => $type, 'id' => $id];
    }

    /**
     * @param mixed $data
     *
     * @return list<array{type: string, id: string}>
     */
    private function validateToManyData(RelationshipMetadata $metadata, ?string $expectedType, mixed $data, string $pointer, string $method): array
    {
        $allowed = ['PATCH', 'POST', 'DELETE'];

        if (!in_array($method, $allowed, true)) {
            throw new MethodNotAllowedException($allowed, sprintf('Method %s is not allowed for relationship "%s".', $method, $metadata->name), [$this->errors->methodNotAllowed($allowed)]);
        }

        if (!is_array($data) || !array_is_list($data)) {
            throw new BadRequestException('Relationship data must be an array of resource identifier objects.', [$this->errors->invalidPointer($pointer, 'Relationship data must be an array of resource identifier objects.')]);
        }

        $unique = [];
        $seen = [];
        $badRequestErrors = [];
        $conflictErrors = [];
        $notFoundErrors = [];

        foreach ($data as $index => $item) {
            $entryPointer = sprintf('%s/%d', $pointer, $index);

            if (!is_array($item) || array_is_list($item)) {
                $badRequestErrors[] = $this->errors->invalidPointer($entryPointer, sprintf('Relationship data entry at index %d must be an object.', $index));
                continue;
            }

            $type = $item['type'] ?? null;
            $id = $item['id'] ?? null;

            if (!is_string($type) || $type === '') {
                $badRequestErrors[] = $this->errors->invalidPointer($entryPointer . '/type', sprintf('Relationship data entry at index %d must contain a non-empty "type".', $index));
                continue;
            }

            if (!is_string($id) || $id === '') {
                $badRequestErrors[] = $this->errors->invalidPointer($entryPointer . '/id', sprintf('Relationship data entry at index %d must contain a non-empty "id".', $index));
                continue;
            }

            if ($expectedType !== null && $expectedType !== $type) {
                $conflictErrors[] = $this->errors->invalidPointer(
                    $entryPointer . '/type',
                    sprintf('Relationship "%s" must reference resources of type "%s".', $metadata->name, $expectedType),
                    '409',
                    ErrorCodes::TYPE_MISMATCH,
                );
                continue;
            }

            if (!$this->exists->exists($type, $id)) {
                $notFoundErrors[] = $this->errors->notFound(sprintf('Related resource "%s" with id "%s" was not found.', $type, $id), $entryPointer);
                continue;
            }

            $key = $type . ':' . $id;
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = ['type' => $type, 'id' => $id];
        }

        if ($badRequestErrors !== []) {
            if (count($badRequestErrors) === 1) {
                throw new BadRequestException('Relationship data is invalid.', [$badRequestErrors[0]]);
            }

            throw new MultiErrorException(400, $badRequestErrors, 'Relationship data is invalid.');
        }

        if ($conflictErrors !== []) {
            if (count($conflictErrors) === 1) {
                throw new ConflictException('Relationship data conflicts with relationship definition.', [$conflictErrors[0]]);
            }

            throw new MultiErrorException(409, $conflictErrors, 'Relationship data conflicts with relationship definition.');
        }

        if ($notFoundErrors !== []) {
            if (count($notFoundErrors) === 1) {
                throw new NotFoundException('Related resources were not found.', [$notFoundErrors[0]]);
            }

            throw new MultiErrorException(404, $notFoundErrors, 'Related resources were not found.');
        }

        return $unique;
    }
}
