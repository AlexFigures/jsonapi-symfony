<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Write;

use JsonApi\Symfony\Http\Error\ErrorMapper;
use JsonApi\Symfony\Http\Exception\BadRequestException;
use JsonApi\Symfony\Http\Exception\ConflictException;
use JsonApi\Symfony\Http\Exception\MultiErrorException;
use JsonApi\Symfony\Http\Exception\NotFoundException;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;

final class InputDocumentValidator
{
    public function __construct(
        private readonly ResourceRegistryInterface $registry,
        private readonly WriteConfig $config,
        private readonly ErrorMapper $errors,
    ) {
    }

    /**
     * @param array<int|string, mixed> $payload
     *
     * @return array{type: string, id: ?string, attributes: array<string, mixed>, relationships: array<string, mixed>}
     */
    public function validateAndExtract(string $routeType, ?string $routeId, array $payload, string $method): array
    {
        if (!$this->registry->hasType($routeType)) {
            throw new NotFoundException('Resource type not found.', [$this->errors->unknownType($routeType)]);
        }

        if (!isset($payload['data'])) {
            throw new BadRequestException('Document is invalid.', [$this->errors->invalidPointer('/data', 'Document must contain a "data" member.')]);
        }

        if (!is_array($payload['data']) || array_is_list($payload['data'])) {
            throw new BadRequestException('Document is invalid.', [$this->errors->invalidPointer('/data', 'The "data" member must be an object.')]);
        }

        /** @var array<string, mixed> $data */
        $data = $payload['data'];

        $type = $data['type'] ?? null;
        if (!is_string($type) || $type === '') {
            throw new BadRequestException('Document is invalid.', [$this->errors->invalidPointer('/data/type', 'Resource type must be a non-empty string.')]);
        }

        if ($type !== $routeType) {
            throw new ConflictException('Resource type does not match the endpoint.', [$this->errors->typeMismatch($routeType, $type)]);
        }

        $id = null;
        if (array_key_exists('id', $data)) {
            if (!is_string($data['id']) || $data['id'] === '') {
                throw new BadRequestException('Document is invalid.', [$this->errors->invalidPointer('/data/id', 'Resource id must be a non-empty string.')]);
            }

            $id = $data['id'];
        }

        if ($method === 'PATCH') {
            if ($id === null) {
                throw new ConflictException('PATCH requests require a resource id.', [$this->errors->idMismatch($routeId ?? '', null)]);
            }

            if ($routeId === null || $id !== $routeId) {
                throw new ConflictException('Resource id does not match the endpoint.', [$this->errors->idMismatch($routeId ?? '', $id)]);
            }
        }

        $relationships = [];
        if (array_key_exists('relationships', $data)) {
            if (!is_array($data['relationships']) || array_is_list($data['relationships'])) {
                throw new BadRequestException('Document is invalid.', [$this->errors->invalidPointer('/data/relationships', 'The "relationships" member must be an object.')]);
            }

            /** @var array<string, mixed> $relationships */
            $relationships = $data['relationships'];

            if ($relationships !== [] && !$this->config->allowRelationshipWrites) {
                throw new BadRequestException('Document is invalid.', [$this->errors->invalidPointer('/data/relationships', 'Writing relationships is not allowed.')]);
            }
        }

        $attributes = [];
        if (array_key_exists('attributes', $data)) {
            if (!is_array($data['attributes']) || array_is_list($data['attributes'])) {
                throw new BadRequestException('Document is invalid.', [$this->errors->invalidPointer('/data/attributes', 'The "attributes" member must be an object.')]);
            }

            /** @var array<string, mixed> $attributes */
            $attributes = $data['attributes'];
        }

        $metadata = $this->registry->getByType($routeType);
        $attributeErrors = [];

        foreach (array_keys($attributes) as $name) {
            if ($name === '') {
                $attributeErrors[] = $this->errors->invalidPointer('/data/attributes', 'Attribute names must be non-empty strings.');
                continue;
            }

            if (!isset($metadata->attributes[$name])) {
                $attributeErrors[] = $this->errors->unknownAttribute($routeType, $name);
                continue;
            }

        }

        if ($attributeErrors !== []) {
            if (count($attributeErrors) === 1) {
                throw new BadRequestException('Attributes validation failed.', [$attributeErrors[0]]);
            }

            throw new MultiErrorException(400, $attributeErrors, 'Attributes validation failed.');
        }

        return [
            'type' => $type,
            'id' => $id,
            'attributes' => $attributes,
            'relationships' => $relationships,
        ];
    }
}
