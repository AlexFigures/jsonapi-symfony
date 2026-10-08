<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Http\Write;

use AlexFigures\JsonApi\Http\Error\ErrorMapper;
use AlexFigures\JsonApi\Http\Exception\BadRequestException;
use AlexFigures\JsonApi\Http\Exception\ConflictException;
use AlexFigures\JsonApi\Http\Exception\MultiErrorException;
use AlexFigures\JsonApi\Http\Exception\NotFoundException;
use AlexFigures\JsonApi\Resource\Registry\ResourceRegistryInterface;

/** @internal */
final readonly class InputDocumentValidator
{
    public function __construct(
        private ResourceRegistryInterface $registry,
        private WriteConfig $config,
        private ErrorMapper $errors,
    ) {
    }

    /**
     * @param array<int|string, mixed> $payload
     *
     * @return array{
     *     type: string,
     *     id: ?string,
     *     attributes: array<string, mixed>,
     *     relationships: array<string, array{data: mixed}>
     * }
     */
    public function validateAndExtract(string $routeType, ?string $routeId, array $payload, string $method, bool $allowLid = false): array
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

        $metadata = $this->registry->getByType($routeType);
        /** @var array<string, array{data: mixed}> $relationships */
        $relationships = [];
        $relationshipErrors = [];
        if (array_key_exists('relationships', $data)) {
            if ($data['relationships'] instanceof \stdClass) {
                $data['relationships'] = [];
            } elseif ($data['relationships'] === []) {
                throw new BadRequestException('Relationships must be an object.', [$this->errors->invalidPointer('/data/relationships', 'Relationships must be an object.')]);
            }
            if (!is_array($data['relationships']) || ($data['relationships'] !== [] && array_is_list($data['relationships']))) {
                throw new BadRequestException('Document is invalid.', [$this->errors->invalidPointer('/data/relationships', 'The "relationships" member must be an object.')]);
            }

            /** @var array<string, mixed> $rawRelationships */
            $rawRelationships = $data['relationships'];

            if ($rawRelationships !== [] && !$this->config->allowRelationshipWrites) {
                throw new BadRequestException('Document is invalid.', [$this->errors->invalidPointer('/data/relationships', 'Writing relationships is not allowed.')]);
            }

            foreach ($rawRelationships as $name => $relationship) {
                if ($name === '') {
                    $relationshipErrors[] = $this->errors->invalidPointer('/data/relationships', 'Relationship names must be non-empty strings.');
                    continue;
                }

                if (!is_array($relationship) || array_is_list($relationship)) {
                    $relationshipErrors[] = $this->errors->invalidPointer(
                        sprintf('/data/relationships/%s', $name),
                        'Relationship must be an object containing a "data" member.'
                    );
                    continue;
                }

                if (!array_key_exists('data', $relationship)) {
                    $relationshipErrors[] = $this->errors->invalidPointer(
                        sprintf('/data/relationships/%s', $name),
                        'Relationship object must contain a "data" member.'
                    );
                    continue;
                }

                if (!isset($metadata->relationships[$name])) {
                    $relationshipErrors[] = $this->errors->invalidPointer('/data/relationships/' . $name, 'Unknown relationship.');
                    continue;
                }
                $relMetadata = $metadata->relationships[$name];
                $linkage = $relationship['data'];
                $pointer = '/data/relationships/' . $name . '/data';
                if ($relMetadata->toMany ? (!is_array($linkage) || !array_is_list($linkage))
                    : ($linkage !== null && (!is_array($linkage) || array_is_list($linkage)))) {
                    $relationshipErrors[] = $this->errors->invalidPointer($pointer, 'Invalid relationship cardinality.');
                    continue;
                }
                $identifiers = $relMetadata->toMany ? $linkage : ($linkage === null ? [] : [$linkage]);
                foreach ($identifiers as $index => $identifier) {
                    $entry = $pointer . ($relMetadata->toMany ? '/' . $index : '');
                    RelationshipIdentifierValidator::validate($identifier, $relMetadata->targetType, $entry, $this->errors, $allowLid);
                }
                $relationships[$name] = ['data' => $linkage];
            }
        }

        $attributes = [];
        if (array_key_exists('attributes', $data)) {
            if ($data['attributes'] instanceof \stdClass) {
                $data['attributes'] = [];
            } elseif ($data['attributes'] === []) {
                throw new BadRequestException('Attributes must be an object.', [$this->errors->invalidPointer('/data/attributes', 'Attributes must be an object.')]);
            }
            if (!is_array($data['attributes']) || ($data['attributes'] !== [] && array_is_list($data['attributes']))) {
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

        $allErrors = array_merge($attributeErrors, $relationshipErrors);

        if ($allErrors !== []) {
            if (count($allErrors) === 1) {
                throw new BadRequestException('Payload validation failed.', [$allErrors[0]]);
            }

            throw new MultiErrorException(400, $allErrors, 'Payload validation failed.');
        }

        return [
            'type' => $type,
            'id' => $id,
            'attributes' => $attributes,
            'relationships' => $relationships,
        ];
    }
}
