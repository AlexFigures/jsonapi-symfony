<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Docs\OpenApi;

use JsonApi\Symfony\Http\Negotiation\MediaType;
use JsonApi\Symfony\Resource\Metadata\AttributeMetadata;
use JsonApi\Symfony\Resource\Metadata\RelationshipMetadata;
use JsonApi\Symfony\Resource\Metadata\ResourceMetadata;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;
use LogicException;

final class OpenApiSpecGenerator
{
    /**
     * @param array{enabled: bool, route: string, title: string, version: string, servers: list<string>} $config
     */
    public function __construct(
        private readonly ResourceRegistryInterface $registry,
        private readonly array $config,
        private readonly string $routePrefix,
        private readonly string $relationshipWriteMode,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function generate(): array
    {
        if (($this->config['enabled'] ?? false) !== true) {
            throw new LogicException('OpenAPI generator is disabled.');
        }

        $schemas = $this->baseSchemas();
        $paths = [];
        $tags = [];

        foreach ($this->registry->all() as $metadata) {
            $names = $this->schemaNames($metadata);

            $schemas[$names['identifier']] = $this->buildIdentifierSchema($metadata);
            $schemas[$names['resource']] = $this->buildResourceSchema($metadata);
            $schemas[$names['resourceDocument']] = $this->buildResourceDocumentSchema($names['resource'], false);
            $schemas[$names['nullableResourceDocument']] = $this->buildResourceDocumentSchema($names['resource'], true);
            $schemas[$names['collectionDocument']] = $this->buildCollectionDocumentSchema($names['resource']);

            $this->addRelationshipSchemas($metadata, $schemas);

            $paths = $this->mergePaths($paths, $this->buildCollectionPaths($metadata, $names));
            $paths = $this->mergePaths($paths, $this->buildResourcePaths($metadata, $names));
            $paths = $this->mergePaths($paths, $this->buildRelationshipPaths($metadata));

            $tags[] = array_filter([
                'name' => $metadata->type,
                'description' => $metadata->description,
            ], static fn ($value) => $value !== null && $value !== '');
        }

        ksort($paths);
        ksort($schemas);

        return array_filter([
            'openapi' => '3.1.0',
            'info' => [
                'title' => $this->config['title'] ?? 'API',
                'version' => $this->config['version'] ?? '1.0.0',
            ],
            'servers' => array_map(
                static fn (string $url): array => ['url' => $url],
                $this->config['servers'] ?? [],
            ),
            'tags' => $tags,
            'paths' => $paths,
            'components' => [
                'schemas' => $schemas,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function baseSchemas(): array
    {
        return [
            'JsonApiVersion' => [
                'type' => 'object',
                'properties' => [
                    'version' => [
                        'type' => 'string',
                        'enum' => ['1.1'],
                    ],
                ],
                'required' => ['version'],
                'additionalProperties' => true,
            ],
            'JsonApiLinks' => [
                'type' => 'object',
                'additionalProperties' => [
                    'type' => 'string',
                    'format' => 'uri',
                ],
            ],
            'JsonApiMeta' => [
                'type' => 'object',
                'additionalProperties' => true,
            ],
        ];
    }

    /**
     * @return array{identifier: string, resource: string, resourceDocument: string, nullableResourceDocument: string, collectionDocument: string}
     */
    private function schemaNames(ResourceMetadata $metadata): array
    {
        $studly = $this->studly($metadata->type);

        return [
            'identifier' => $studly . 'Identifier',
            'resource' => $studly . 'Resource',
            'resourceDocument' => $studly . 'ResourceDocument',
            'nullableResourceDocument' => $studly . 'NullableResourceDocument',
            'collectionDocument' => $studly . 'CollectionDocument',
        ];
    }

    /**
     * @param array<string, mixed> $paths
     * @param array<string, mixed> $additional
     *
     * @return array<string, mixed>
     */
    private function mergePaths(array $paths, array $additional): array
    {
        foreach ($additional as $path => $operations) {
            if (!isset($paths[$path])) {
                $paths[$path] = $operations;

                continue;
            }

            $paths[$path] = array_merge($paths[$path], $operations);
        }

        return $paths;
    }

    private function collectionPath(ResourceMetadata $metadata): string
    {
        $base = $metadata->routePrefix ?? $this->routePrefix;
        $base = $base === '' ? '/' : rtrim($base, '/');
        $typeSegment = '/' . $metadata->type;

        if ($base === '/') {
            return $typeSegment;
        }

        if (str_ends_with($base, $typeSegment)) {
            return $base;
        }

        return $base . $typeSegment;
    }

    /**
     * @param array{identifier: string, resource: string, resourceDocument: string, nullableResourceDocument: string, collectionDocument: string} $names
     *
     * @return array<string, mixed>
     */
    private function buildCollectionPaths(ResourceMetadata $metadata, array $names): array
    {
        $path = $this->collectionPath($metadata);
        $tag = $metadata->type;
        $collectionRef = '#/components/schemas/' . $names['collectionDocument'];
        $resourceRef = '#/components/schemas/' . $names['resourceDocument'];

        return [
            $path => [
                'get' => [
                    'tags' => [$tag],
                    'operationId' => 'list' . $this->studly($metadata->type),
                    'summary' => sprintf('List %s resources', $metadata->type),
                    'responses' => [
                        '200' => [
                            'description' => 'Successful response',
                            'content' => [
                                MediaType::JSON_API => [
                                    'schema' => ['$ref' => $collectionRef],
                                ],
                            ],
                        ],
                    ],
                ],
                'post' => [
                    'tags' => [$tag],
                    'operationId' => 'create' . $this->studly($metadata->type),
                    'summary' => sprintf('Create a %s resource', $metadata->type),
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            MediaType::JSON_API => [
                                'schema' => ['$ref' => $resourceRef],
                            ],
                        ],
                    ],
                    'responses' => [
                        '201' => [
                            'description' => 'Resource created',
                            'headers' => [
                                'Location' => [
                                    'schema' => [
                                        'type' => 'string',
                                        'format' => 'uri',
                                    ],
                                    'description' => 'URI of the created resource',
                                ],
                            ],
                            'content' => [
                                MediaType::JSON_API => [
                                    'schema' => ['$ref' => $resourceRef],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array{identifier: string, resource: string, resourceDocument: string, nullableResourceDocument: string, collectionDocument: string} $names
     *
     * @return array<string, mixed>
     */
    private function buildResourcePaths(ResourceMetadata $metadata, array $names): array
    {
        $path = $this->collectionPath($metadata) . '/{id}';
        $tag = $metadata->type;
        $resourceRef = '#/components/schemas/' . $names['resourceDocument'];

        return [
            $path => [
                'parameters' => [$this->idParameter()],
                'get' => [
                    'tags' => [$tag],
                    'operationId' => 'get' . $this->studly($metadata->type),
                    'summary' => sprintf('Fetch a %s resource', $metadata->type),
                    'responses' => [
                        '200' => [
                            'description' => 'Successful response',
                            'content' => [
                                MediaType::JSON_API => [
                                    'schema' => ['$ref' => $resourceRef],
                                ],
                            ],
                        ],
                    ],
                ],
                'patch' => [
                    'tags' => [$tag],
                    'operationId' => 'update' . $this->studly($metadata->type),
                    'summary' => sprintf('Update a %s resource', $metadata->type),
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            MediaType::JSON_API => [
                                'schema' => ['$ref' => $resourceRef],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Resource updated',
                            'content' => [
                                MediaType::JSON_API => [
                                    'schema' => ['$ref' => $resourceRef],
                                ],
                            ],
                        ],
                    ],
                ],
                'delete' => [
                    'tags' => [$tag],
                    'operationId' => 'delete' . $this->studly($metadata->type),
                    'summary' => sprintf('Delete a %s resource', $metadata->type),
                    'responses' => [
                        '204' => ['description' => 'Resource deleted'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRelationshipPaths(ResourceMetadata $metadata): array
    {
        if ($metadata->relationships === []) {
            return [];
        }

        $paths = [];
        $base = $this->collectionPath($metadata) . '/{id}';
        $tag = $metadata->type;

        foreach ($metadata->relationships as $relationship) {
            $relationshipDocName = $this->relationshipDocumentName($metadata, $relationship);
            $relationshipRef = '#/components/schemas/' . $relationshipDocName;
            $responses = $this->relationshipWriteResponses($relationshipRef);

            $relationshipPath = sprintf('%s/relationships/%s', $base, $relationship->name);
            $paths[$relationshipPath] = [
                'parameters' => [$this->idParameter()],
                'get' => $this->buildRelationshipGetOperation($relationship, $tag, $relationshipRef),
                'patch' => $this->buildRelationshipWriteOperation($relationship, $tag, 'update', $relationshipRef, $responses),
            ];

            if ($relationship->toMany) {
                $paths[$relationshipPath]['post'] = $this->buildRelationshipWriteOperation($relationship, $tag, 'add', $relationshipRef, $responses);
                $paths[$relationshipPath]['delete'] = $this->buildRelationshipWriteOperation($relationship, $tag, 'remove', $relationshipRef, $responses);
            }

            $relatedPath = sprintf('%s/%s', $base, $relationship->name);
            $paths[$relatedPath] = [
                'parameters' => [$this->idParameter()],
                'get' => $relationship->toMany
                    ? $this->buildRelatedCollectionOperation($relationship, $tag)
                    : $this->buildRelatedResourceOperation($relationship, $tag),
            ];
        }

        return $paths;
    }

    private function buildRelationshipGetOperation(RelationshipMetadata $relationship, string $tag, string $relationshipRef): array
    {
        return [
            'tags' => [$tag],
            'operationId' => sprintf(
                'get%s%sRelationship',
                $this->studly($tag),
                $this->studly($relationship->name),
            ),
            'summary' => sprintf('Fetch the %s relationship', $relationship->name),
            'responses' => [
                '200' => [
                    'description' => 'Relationship linkage',
                    'content' => [
                        MediaType::JSON_API => [
                            'schema' => ['$ref' => $relationshipRef],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function buildRelationshipWriteOperation(
        RelationshipMetadata $relationship,
        string $tag,
        string $action,
        string $relationshipRef,
        array $responses,
    ): array {
        return [
            'tags' => [$tag],
            'operationId' => sprintf(
                '%s%s%sRelationship',
                $action,
                $this->studly($tag),
                $this->studly($relationship->name),
            ),
            'summary' => sprintf('%s the %s relationship', ucfirst($action), $relationship->name),
            'requestBody' => [
                'required' => true,
                'content' => [
                    MediaType::JSON_API => [
                        'schema' => ['$ref' => $relationshipRef],
                    ],
                ],
            ],
            'responses' => $responses,
        ];
    }

    private function buildRelatedCollectionOperation(RelationshipMetadata $relationship, string $tag): array
    {
        $target = $this->resolveRelationshipTarget($relationship);
        $schema = $target === null
            ? ['type' => 'object']
            : ['$ref' => '#/components/schemas/' . $this->studly($target) . 'CollectionDocument'];

        return [
            'tags' => [$tag],
            'operationId' => sprintf(
                'get%s%sRelated',
                $this->studly($tag),
                $this->studly($relationship->name),
            ),
            'summary' => sprintf('Fetch related resources for %s', $relationship->name),
            'responses' => [
                '200' => [
                    'description' => 'Related resources',
                    'content' => [
                        MediaType::JSON_API => [
                            'schema' => $schema,
                        ],
                    ],
                ],
            ],
        ];
    }

    private function buildRelatedResourceOperation(RelationshipMetadata $relationship, string $tag): array
    {
        $target = $this->resolveRelationshipTarget($relationship);
        $schema = $target === null
            ? ['type' => 'object']
            : ['$ref' => '#/components/schemas/' . $this->studly($target) . 'NullableResourceDocument'];

        return [
            'tags' => [$tag],
            'operationId' => sprintf(
                'get%s%sRelated',
                $this->studly($tag),
                $this->studly($relationship->name),
            ),
            'summary' => sprintf('Fetch the related %s resource', $relationship->name),
            'responses' => [
                '200' => [
                    'description' => 'Related resource',
                    'content' => [
                        MediaType::JSON_API => [
                            'schema' => $schema,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function relationshipWriteResponses(string $relationshipRef): array
    {
        if ($this->relationshipWriteMode === '204') {
            return ['204' => ['description' => 'Relationship updated']];
        }

        return [
            '200' => [
                'description' => 'Relationship updated',
                'content' => [
                    MediaType::JSON_API => [
                        'schema' => ['$ref' => $relationshipRef],
                    ],
                ],
            ],
            '204' => ['description' => 'Relationship updated'],
        ];
    }

    private function relationshipDocumentName(ResourceMetadata $resource, RelationshipMetadata $relationship): string
    {
        return sprintf(
            '%s%sRelationshipDocument',
            $this->studly($resource->type),
            $this->studly($relationship->name),
        );
    }

    private function buildIdentifierSchema(ResourceMetadata $metadata): array
    {
        $required = ['type'];
        if ($metadata->exposeId) {
            $required[] = 'id';
        }

        return [
            'type' => 'object',
            'required' => $required,
            'properties' => [
                'type' => [
                    'type' => 'string',
                    'const' => $metadata->type,
                ],
                'id' => [
                    'type' => 'string',
                    'nullable' => !$metadata->exposeId,
                ],
            ],
            'additionalProperties' => false,
        ];
    }

    private function buildResourceSchema(ResourceMetadata $metadata): array
    {
        $attributes = [];
        foreach ($metadata->attributes as $attribute) {
            $attributes[$attribute->name] = $this->attributeSchema($attribute);
        }

        $relationships = [];
        foreach ($metadata->relationships as $relationship) {
            $relationships[$relationship->name] = $this->relationshipSchema($relationship);
        }

        $required = ['type', 'attributes'];
        if ($metadata->exposeId) {
            $required[] = 'id';
        }

        return [
            'type' => 'object',
            'required' => $required,
            'properties' => [
                'type' => [
                    'type' => 'string',
                    'const' => $metadata->type,
                ],
                'id' => [
                    'type' => 'string',
                    'nullable' => !$metadata->exposeId,
                ],
                'attributes' => [
                    'type' => 'object',
                    'properties' => $attributes,
                    'additionalProperties' => true,
                ],
                'relationships' => [
                    'type' => 'object',
                    'properties' => $relationships,
                    'additionalProperties' => true,
                ],
                'links' => ['$ref' => '#/components/schemas/JsonApiLinks'],
                'meta' => ['$ref' => '#/components/schemas/JsonApiMeta'],
            ],
            'additionalProperties' => true,
        ];
    }

    private function buildResourceDocumentSchema(string $resourceSchemaName, bool $nullable): array
    {
        $dataSchema = ['$ref' => '#/components/schemas/' . $resourceSchemaName];
        if ($nullable) {
            $dataSchema['nullable'] = true;
        }

        return [
            'type' => 'object',
            'required' => ['data'],
            'properties' => [
                'jsonapi' => ['$ref' => '#/components/schemas/JsonApiVersion'],
                'links' => ['$ref' => '#/components/schemas/JsonApiLinks'],
                'data' => $dataSchema,
                'included' => [
                    'type' => 'array',
                    'items' => ['type' => 'object'],
                ],
                'meta' => ['$ref' => '#/components/schemas/JsonApiMeta'],
            ],
            'additionalProperties' => true,
        ];
    }

    private function buildCollectionDocumentSchema(string $resourceSchemaName): array
    {
        return [
            'type' => 'object',
            'required' => ['data'],
            'properties' => [
                'jsonapi' => ['$ref' => '#/components/schemas/JsonApiVersion'],
                'links' => ['$ref' => '#/components/schemas/JsonApiLinks'],
                'data' => [
                    'type' => 'array',
                    'items' => ['$ref' => '#/components/schemas/' . $resourceSchemaName],
                ],
                'meta' => ['$ref' => '#/components/schemas/JsonApiMeta'],
            ],
            'additionalProperties' => true,
        ];
    }

    private function attributeSchema(AttributeMetadata $attribute): array
    {
        if ($attribute->types === []) {
            $schema = ['type' => 'string'];
        } elseif (count($attribute->types) === 1) {
            $schema = $this->mapType($attribute->types[0]);
        } else {
            $schema = [
                'oneOf' => array_map(fn (string $type) => $this->mapType($type), $attribute->types),
            ];
        }

        if ($attribute->nullable) {
            $schema['nullable'] = true;
        }

        return $schema;
    }

    private function relationshipSchema(RelationshipMetadata $relationship): array
    {
        $target = $this->resolveRelationshipTarget($relationship);
        $identifierRef = $target === null
            ? ['type' => 'object']
            : ['$ref' => '#/components/schemas/' . $this->studly($target) . 'Identifier'];

        if ($relationship->toMany) {
            $data = [
                'type' => 'array',
                'items' => $identifierRef,
            ];
        } else {
            $data = $identifierRef;
            if ($relationship->nullable) {
                $data['nullable'] = true;
            }
        }

        return [
            'type' => 'object',
            'properties' => [
                'links' => ['$ref' => '#/components/schemas/JsonApiLinks'],
                'data' => $data,
                'meta' => ['$ref' => '#/components/schemas/JsonApiMeta'],
            ],
            'additionalProperties' => true,
        ];
    }

    private function resolveRelationshipTarget(RelationshipMetadata $relationship): ?string
    {
        if ($relationship->targetType !== null) {
            return $relationship->targetType;
        }

        if ($relationship->targetClass === null) {
            return null;
        }

        return $this->registry->getByClass($relationship->targetClass)?->type;
    }

    private function mapType(string $type): array
    {
        $normalized = strtolower($type);

        if ($normalized === 'int' || $normalized === 'integer') {
            return ['type' => 'integer'];
        }

        if ($normalized === 'float' || $normalized === 'double') {
            return ['type' => 'number', 'format' => 'float'];
        }

        if ($normalized === 'bool' || $normalized === 'boolean') {
            return ['type' => 'boolean'];
        }

        if ($normalized === 'array') {
            return [
                'type' => 'array',
                'items' => ['type' => 'object'],
            ];
        }

        if ($normalized === 'string') {
            return ['type' => 'string'];
        }

        if (is_a($type, \DateTimeInterface::class, true)) {
            return ['type' => 'string', 'format' => 'date-time'];
        }

        if (enum_exists($type)) {
            $cases = array_map(
                static fn (\UnitEnum $case) => $case instanceof \BackedEnum ? $case->value : $case->name,
                $type::cases(),
            );

            return ['type' => 'string', 'enum' => $cases];
        }

        return ['type' => 'object'];
    }

    private function relationshipDocumentSchema(ResourceMetadata $resource, RelationshipMetadata $relationship): array
    {
        $target = $this->resolveRelationshipTarget($relationship);
        $identifierRef = $target === null
            ? ['type' => 'object']
            : ['$ref' => '#/components/schemas/' . $this->studly($target) . 'Identifier'];

        if ($relationship->toMany) {
            $data = [
                'type' => 'array',
                'items' => $identifierRef,
            ];
        } else {
            $data = $identifierRef;
            if ($relationship->nullable) {
                $data['nullable'] = true;
            }
        }

        return [
            'type' => 'object',
            'required' => ['data'],
            'properties' => [
                'jsonapi' => ['$ref' => '#/components/schemas/JsonApiVersion'],
                'links' => ['$ref' => '#/components/schemas/JsonApiLinks'],
                'data' => $data,
                'meta' => ['$ref' => '#/components/schemas/JsonApiMeta'],
            ],
            'additionalProperties' => true,
        ];
    }

    /**
     * @param array<string, mixed> $schemas
     */
    private function addRelationshipSchemas(ResourceMetadata $metadata, array &$schemas): void
    {
        foreach ($metadata->relationships as $relationship) {
            $schemas[$this->relationshipDocumentName($metadata, $relationship)] = $this->relationshipDocumentSchema($metadata, $relationship);
        }
    }

    private function studly(string $value): string
    {
        $segments = preg_split('/[^a-zA-Z0-9]+/', $value, -1, PREG_SPLIT_NO_EMPTY);
        if ($segments === false || $segments === []) {
            return ucfirst($value);
        }

        return implode('', array_map(static fn (string $segment): string => ucfirst(strtolower($segment)), $segments));
    }

    /**
     * @return array<string, string|array<string, mixed>>
     */
    private function idParameter(): array
    {
        return [
            'name' => 'id',
            'in' => 'path',
            'required' => true,
            'schema' => ['type' => 'string'],
            'description' => 'Resource identifier',
        ];
    }
}
