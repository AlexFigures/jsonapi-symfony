<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Write;

use JsonApi\Symfony\Http\Exception\BadRequestException;
use JsonApi\Symfony\Http\Exception\ConflictException;
use JsonApi\Symfony\Http\Exception\NotFoundException;
use JsonApi\Symfony\Resource\Metadata\AttributeMetadata;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;

final class InputDocumentValidator
{
    public function __construct(
        private readonly ResourceRegistryInterface $registry,
        private readonly WriteConfig $config,
    ) {
    }

    /**
     * @return array{type: string, id: ?string, attributes: array<string, mixed>, relationships: array<string, mixed>}
     */
    public function validateAndExtract(string $routeType, ?string $routeId, array $payload, string $method): array
    {
        if (!$this->registry->hasType($routeType)) {
            throw new NotFoundException(sprintf('Resource type "%s" not found.', $routeType));
        }

        if (!isset($payload['data'])) {
            throw new BadRequestException('Document must contain a "data" member.');
        }

        if (!is_array($payload['data'])) {
            throw new BadRequestException('The "data" member must be an object.');
        }

        /** @var array<string, mixed> $data */
        $data = $payload['data'];

        $type = $data['type'] ?? null;
        if (!is_string($type) || $type === '') {
            throw new BadRequestException('Resource type must be a non-empty string.');
        }

        if ($type !== $routeType) {
            throw new ConflictException('Resource type does not match the endpoint.');
        }

        $id = null;
        if (array_key_exists('id', $data)) {
            if (!is_string($data['id']) || $data['id'] === '') {
                throw new BadRequestException('Resource id must be a non-empty string.');
            }

            $id = $data['id'];
        }

        if ($method === 'PATCH') {
            if ($id === null) {
                throw new ConflictException('PATCH requests require a resource id.');
            }

            if ($routeId === null || $id !== $routeId) {
                throw new ConflictException('Resource id does not match the endpoint.');
            }
        }

        $relationships = [];
        if (array_key_exists('relationships', $data)) {
            if (!is_array($data['relationships'])) {
                throw new BadRequestException('The "relationships" member must be an object.');
            }

            /** @var array<string, mixed> $relationships */
            $relationships = $data['relationships'];

            if ($relationships !== [] && !$this->config->allowRelationshipWrites) {
                throw new BadRequestException('Writing relationships is not allowed.');
            }

            if ($relationships !== [] && array_is_list($relationships)) {
                throw new BadRequestException('The "relationships" member must be an object.');
            }
        }

        $attributes = [];
        if (array_key_exists('attributes', $data)) {
            if (!is_array($data['attributes'])) {
                throw new BadRequestException('The "attributes" member must be an object.');
            }

            /** @var array<string, mixed> $attributes */
            $attributes = $data['attributes'];
        }

        if ($attributes !== [] && array_is_list($attributes)) {
            throw new BadRequestException('The "attributes" member must be an object.');
        }

        $metadata = $this->registry->getByType($routeType);

        foreach ($attributes as $name => $_) {
            if (!isset($metadata->attributes[$name])) {
                throw new BadRequestException(sprintf('Unknown attribute "%s" for type "%s".', $name, $routeType));
            }

            /** @var AttributeMetadata $attribute */
            $attribute = $metadata->attributes[$name];
            if (!$attribute->writable) {
                throw new BadRequestException(sprintf('Attribute "%s" is read-only.', $name));
            }
        }

        return [
            'type' => $type,
            'id' => $id,
            'attributes' => $attributes,
            'relationships' => $relationships,
        ];
    }
}
