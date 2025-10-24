<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Docs\OpenApi;

use AlexFigures\Symfony\Atomic\AtomicConfig;
use AlexFigures\Symfony\Http\Negotiation\MediaType;
use AlexFigures\Symfony\Resource\Metadata\AttributeMetadata;
use AlexFigures\Symfony\Resource\Metadata\CustomRouteMetadata;
use AlexFigures\Symfony\Resource\Metadata\RelationshipMetadata;
use AlexFigures\Symfony\Resource\Metadata\ResourceMetadata;
use AlexFigures\Symfony\Resource\Registry\CustomRouteRegistryInterface;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
use LogicException;

/**
 * @phpstan-type OpenApiServer array{url: string}
 * @phpstan-type OpenApiTag array{name: string, description?: string}
 * @phpstan-type OpenApiSchema array<string, mixed>
 * @phpstan-type OpenApiPaths array<string, OpenApiSchema>
 * @phpstan-type OpenApiComponents array{schemas: array<string, OpenApiSchema>}
 * @phpstan-type OpenApiDocument array{
 *     openapi: '3.1.0',
 *     info: array{title: string, version: string},
 *     servers: list<OpenApiServer>,
 *     tags: list<OpenApiTag>,
 *     paths: array<string, OpenApiSchema>,
 *     components: OpenApiComponents
 * }
 */

final class OpenApiSpecGenerator
{
    /**
     * @param array{enabled: bool, route: string, title: string, version: string, servers: list<string>} $config
     */
    public function __construct(
        private readonly ResourceRegistryInterface $registry,
        private readonly ?CustomRouteRegistryInterface $customRouteRegistry,
        private readonly array $config,
        private readonly string $routePrefix,
        private readonly string $relationshipWriteMode,
        private readonly ?AtomicConfig $atomicConfig = null,
        private readonly ?CustomEndpointCollector $customEndpointCollector = null,
    ) {
    }

    /**
     * @return OpenApiDocument
     */
    public function generate(): array
    {
        if (!$this->config['enabled']) {
            throw new LogicException('OpenAPI generator is disabled.');
        }

        /** @var array<string, OpenApiSchema> $schemas */
        $schemas = $this->baseSchemas();
        /** @var array<string, OpenApiSchema> $paths */
        $paths = [];
        /** @var list<OpenApiTag> $tags */
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

            $tag = ['name' => $metadata->type];
            if ($metadata->description !== null && $metadata->description !== '') {
                $tag['description'] = $metadata->description;
            }

            $tags[] = $tag;
        }

        // Add custom routes
        if ($this->customRouteRegistry !== null) {
            foreach ($this->customRouteRegistry->all() as $customRoute) {
                $paths = $this->mergePaths($paths, $this->buildCustomRoutePaths($customRoute));
            }
        }

        // Add atomic operations endpoint if enabled
        if ($this->atomicConfig !== null && $this->atomicConfig->enabled) {
            $paths = $this->mergePaths($paths, $this->buildAtomicOperationsPaths());
            $schemas = array_merge($schemas, $this->atomicOperationsSchemas());

            // Add Atomic Operations tag
            $tags[] = [
                'name' => 'Atomic Operations',
                'description' => 'JSON:API Atomic Operations for batch processing',
            ];
        }

        // Add custom endpoints with OpenApiEndpoint attribute
        if ($this->customEndpointCollector !== null) {
            foreach ($this->customEndpointCollector->collect() as $endpoint) {
                $paths = $this->mergePaths($paths, $this->buildCustomEndpointPaths($endpoint));

                // Add tags from custom endpoint
                foreach ($endpoint->openApi->tags as $tag) {
                    if (!in_array(['name' => $tag], $tags, true)) {
                        $tags[] = ['name' => $tag];
                    }
                }
            }
        }

        ksort($paths);
        ksort($schemas);

        /** @var OpenApiComponents $components */
        $components = [
            'schemas' => $schemas,
        ];

        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => $this->config['title'],
                'version' => $this->config['version'],
            ],
            'servers' => array_map(
                static fn (string $url): array => ['url' => $url],
                $this->config['servers'],
            ),
            'tags' => $tags,
            'paths' => $paths,
            'components' => $components,
        ];
    }

    /**
     * @return array<string, OpenApiSchema>
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
            'ErrorObject' => [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => 'string',
                        'description' => 'A unique identifier for this particular occurrence of the problem',
                    ],
                    'status' => [
                        'type' => 'string',
                        'description' => 'The HTTP status code applicable to this problem',
                    ],
                    'code' => [
                        'type' => 'string',
                        'description' => 'An application-specific error code',
                    ],
                    'title' => [
                        'type' => 'string',
                        'description' => 'A short, human-readable summary of the problem',
                    ],
                    'detail' => [
                        'type' => 'string',
                        'description' => 'A human-readable explanation specific to this occurrence of the problem',
                    ],
                    'source' => [
                        'type' => 'object',
                        'properties' => [
                            'pointer' => [
                                'type' => 'string',
                                'description' => 'A JSON Pointer to the associated entity in the request document',
                            ],
                            'parameter' => [
                                'type' => 'string',
                                'description' => 'A string indicating which URI query parameter caused the error',
                            ],
                            'header' => [
                                'type' => 'string',
                                'description' => 'A string indicating the name of a single request header which caused the error',
                            ],
                        ],
                        'additionalProperties' => false,
                    ],
                    'meta' => ['$ref' => '#/components/schemas/JsonApiMeta'],
                ],
                'additionalProperties' => false,
            ],
            'ErrorDocument' => [
                'type' => 'object',
                'required' => ['errors'],
                'properties' => [
                    'errors' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/ErrorObject'],
                        'minItems' => 1,
                        'description' => 'An array of error objects',
                    ],
                    'jsonapi' => ['$ref' => '#/components/schemas/JsonApiVersion'],
                    'meta' => ['$ref' => '#/components/schemas/JsonApiMeta'],
                ],
                'additionalProperties' => false,
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
     * @param array<string, OpenApiSchema> $paths
     * @param array<string, OpenApiSchema> $additional
     *
     * @return array<string, OpenApiSchema>
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
     * @return array<string, OpenApiSchema>
     */
    private function buildCollectionPaths(ResourceMetadata $metadata, array $names): array
    {
        $path = $this->collectionPath($metadata);
        $tag = $metadata->type;
        $collectionRef = '#/components/schemas/' . $names['collectionDocument'];
        $resourceRef = '#/components/schemas/' . $names['resourceDocument'];

        $getOperation = [
            'tags' => [$tag],
            'operationId' => 'list' . $this->studly($metadata->type),
            'summary' => sprintf('List %s resources', $metadata->type),
            'parameters' => $this->buildCollectionParameters($metadata),
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
        ];

        return [
            $path => [
                'get' => $getOperation,
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
     * @return array<string, OpenApiSchema>
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
     * @return array<string, OpenApiSchema>
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

    /**
     * @return OpenApiSchema
     */
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

    /**
     * @param array<int, OpenApiSchema> $responses
     *
     * @return OpenApiSchema
     */
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

    /**
     * @return OpenApiSchema
     */
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

    /**
     * @return OpenApiSchema
     */
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
     * @return array<int, OpenApiSchema>
     */
    private function relationshipWriteResponses(string $relationshipRef): array
    {
        if ($this->relationshipWriteMode === '204') {
            return [204 => ['description' => 'Relationship updated']];
        }

        return [
            200 => [
                'description' => 'Relationship updated',
                'content' => [
                    MediaType::JSON_API => [
                        'schema' => ['$ref' => $relationshipRef],
                    ],
                ],
            ],
            204 => ['description' => 'Relationship updated'],
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

    /**
     * @return OpenApiSchema
     */
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

    /**
     * @return OpenApiSchema
     */
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

    /**
     * @return OpenApiSchema
     */
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

    /**
     * @return OpenApiSchema
     */
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

    /**
     * @return OpenApiSchema
     */
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

    /**
     * @return OpenApiSchema
     */
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

    /**
     * @return OpenApiSchema
     */
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

    /**
     * @return OpenApiSchema
     */
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
        $segments = preg_split('/[^a-zA-Z0-9]+/', $value, -1, \PREG_SPLIT_NO_EMPTY);
        if ($segments === false || $segments === []) {
            return ucfirst($value);
        }

        return implode('', array_map(static fn (string $segment): string => ucfirst(strtolower($segment)), $segments));
    }

    /**
     * @return array<string, string|bool|array<string, mixed>>
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

    /**
     * Build OpenAPI paths for a custom route.
     *
     * @return array<string, OpenApiSchema>
     */
    private function buildCustomRoutePaths(CustomRouteMetadata $route): array
    {
        $paths = [];
        $path = $route->path;

        // Extract path parameters
        $parameters = $this->extractPathParameters($path);

        // Add id parameter if present in path
        if (str_contains($path, '{id}')) {
            $parameters[] = $this->idParameter();
        }

        foreach ($route->methods as $method) {
            $methodLower = strtolower($method);
            $operationId = $this->generateCustomRouteOperationId($route);

            $operation = [
                'operationId' => $operationId,
                'summary' => $route->description ?? $this->generateCustomRouteSummary($route),
                'tags' => $route->resourceType !== null ? [$route->resourceType] : [],
                'parameters' => $parameters,
                'responses' => [
                    '200' => [
                        'description' => 'Successful response',
                        'content' => [
                            MediaType::JSON_API => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'data' => [
                                            'oneOf' => [
                                                ['type' => 'object'],
                                                ['type' => 'array', 'items' => ['type' => 'object']],
                                                ['type' => 'null'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '400' => [
                        'description' => 'Bad Request',
                        'content' => [
                            MediaType::JSON_API => [
                                'schema' => ['$ref' => '#/components/schemas/ErrorDocument'],
                            ],
                        ],
                    ],
                    '404' => [
                        'description' => 'Not Found',
                        'content' => [
                            MediaType::JSON_API => [
                                'schema' => ['$ref' => '#/components/schemas/ErrorDocument'],
                            ],
                        ],
                    ],
                ],
            ];

            // Add request body for POST, PUT, PATCH methods
            if (in_array($methodLower, ['post', 'put', 'patch'], true)) {
                $operation['requestBody'] = [
                    'required' => true,
                    'content' => [
                        MediaType::JSON_API => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'data' => [
                                        'type' => 'object',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ];
            }

            $paths[$path][$methodLower] = $operation;
        }

        return $paths;
    }

    /**
     * Generate operation ID for a custom route.
     */
    private function generateCustomRouteOperationId(CustomRouteMetadata $route): string
    {
        // Extract the action from the route name (e.g., 'articles.publish' -> 'publish')
        $parts = explode('.', $route->name);
        $action = end($parts);

        // Convert action to camelCase (e.g., 'publish' -> 'publish', 'bulk-archive' -> 'bulkArchive')
        $actionCamel = lcfirst($this->studly($action));

        // If there's a resource type, include it
        if ($route->resourceType !== null) {
            $resourcePart = $this->studly($route->resourceType);

            // Determine if we should use singular or plural form
            // If the path contains {id}, it's a single resource operation -> use singular
            // Otherwise, it's a collection operation -> use plural
            $useSingular = str_contains($route->path, '{id}');

            if ($useSingular && str_ends_with($resourcePart, 's') && strlen($resourcePart) > 1) {
                // Remove trailing 's' to get singular form
                // This is a simple heuristic - for more complex cases, use a proper inflector
                $resourcePart = substr($resourcePart, 0, -1);
            }

            return $actionCamel . $resourcePart;
        }

        return $actionCamel;
    }

    /**
     * Generate summary for a custom route.
     */
    private function generateCustomRouteSummary(CustomRouteMetadata $route): string
    {
        $parts = explode('.', $route->name);
        $action = end($parts);

        if ($route->resourceType !== null) {
            return ucfirst($action) . ' ' . $route->resourceType;
        }

        return ucfirst($action);
    }

    /**
     * Extract path parameters from a route path.
     *
     * @return list<array<string, mixed>>
     */
    private function extractPathParameters(string $path): array
    {
        $parameters = [];
        preg_match_all('/\{([^}]+)\}/', $path, $matches);

        foreach ($matches[1] as $paramName) {
            // Skip 'id' as it's handled separately
            if ($paramName === 'id') {
                continue;
            }

            $parameters[] = [
                'name' => $paramName,
                'in' => 'path',
                'required' => true,
                'schema' => ['type' => 'string'],
                'description' => ucfirst($paramName) . ' parameter',
            ];
        }

        return $parameters;
    }

    /**
     * Build OpenAPI paths for atomic operations endpoint.
     *
     * @return array<string, OpenApiSchema>
     */
    private function buildAtomicOperationsPaths(): array
    {
        if ($this->atomicConfig === null || !$this->atomicConfig->enabled) {
            return [];
        }

        $endpoint = $this->atomicConfig->endpoint;

        return [
            $endpoint => [
                'post' => [
                    'tags' => ['Atomic Operations'],
                    'operationId' => 'executeAtomicOperations',
                    'summary' => 'Execute atomic operations',
                    'description' => 'Execute multiple JSON:API operations atomically. All operations succeed or all fail together.',
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            MediaType::JSON_API_ATOMIC => [
                                'schema' => ['$ref' => '#/components/schemas/AtomicOperationsRequest'],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Operations executed successfully',
                            'content' => [
                                MediaType::JSON_API_ATOMIC => [
                                    'schema' => ['$ref' => '#/components/schemas/AtomicOperationsResponse'],
                                ],
                            ],
                        ],
                        '204' => [
                            'description' => 'Operations executed successfully with no content',
                        ],
                        '400' => [
                            'description' => 'Bad Request - Invalid operations',
                            'content' => [
                                MediaType::JSON_API => [
                                    'schema' => ['$ref' => '#/components/schemas/ErrorDocument'],
                                ],
                            ],
                        ],
                        '403' => [
                            'description' => 'Forbidden - Missing required ext header',
                            'content' => [
                                MediaType::JSON_API => [
                                    'schema' => ['$ref' => '#/components/schemas/ErrorDocument'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Build schemas for atomic operations.
     *
     * @return array<string, OpenApiSchema>
     */
    private function atomicOperationsSchemas(): array
    {
        return [
            'AtomicOperation' => [
                'type' => 'object',
                'required' => ['op'],
                'properties' => [
                    'op' => [
                        'type' => 'string',
                        'enum' => ['add', 'update', 'remove'],
                        'description' => 'Operation type',
                    ],
                    'ref' => [
                        'type' => 'object',
                        'properties' => [
                            'type' => ['type' => 'string'],
                            'id' => ['type' => 'string'],
                            'relationship' => ['type' => 'string'],
                        ],
                        'description' => 'Reference to the resource or relationship',
                    ],
                    'data' => [
                        'oneOf' => [
                            ['type' => 'object'],
                            ['type' => 'array', 'items' => ['type' => 'object']],
                            ['type' => 'null'],
                        ],
                        'description' => 'Operation data',
                    ],
                    'href' => [
                        'type' => 'string',
                        'format' => 'uri',
                        'description' => 'Alternative to ref - URI reference',
                    ],
                ],
                'additionalProperties' => false,
            ],
            'AtomicOperationsRequest' => [
                'type' => 'object',
                'required' => ['atomic:operations'],
                'properties' => [
                    'atomic:operations' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/AtomicOperation'],
                        'minItems' => 1,
                        'maxItems' => $this->atomicConfig !== null ? $this->atomicConfig->maxOperations : 100,
                        'description' => 'Array of operations to execute atomically',
                    ],
                ],
                'additionalProperties' => false,
            ],
            'AtomicOperationResult' => [
                'type' => 'object',
                'properties' => [
                    'data' => [
                        'oneOf' => [
                            ['type' => 'object'],
                            ['type' => 'array', 'items' => ['type' => 'object']],
                            ['type' => 'null'],
                        ],
                        'description' => 'Result data for the operation',
                    ],
                ],
                'additionalProperties' => true,
            ],
            'AtomicOperationsResponse' => [
                'type' => 'object',
                'required' => ['atomic:results'],
                'properties' => [
                    'atomic:results' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/AtomicOperationResult'],
                        'description' => 'Results for each operation',
                    ],
                ],
                'additionalProperties' => true,
            ],
        ];
    }

    /**
     * Build OpenAPI paths for a custom endpoint with OpenApiEndpoint attribute.
     *
     * @return array<string, OpenApiSchema>
     */
    private function buildCustomEndpointPaths(CustomEndpointMetadata $endpoint): array
    {
        $openApi = $endpoint->openApi;
        $operation = [
            'operationId' => $openApi->operationId ?? $this->generateOperationId($endpoint->path, $endpoint->method),
            'summary' => $openApi->summary,
            'tags' => $openApi->tags,
        ];

        if ($openApi->description !== null) {
            $operation['description'] = $openApi->description;
        }

        if ($openApi->deprecated) {
            $operation['deprecated'] = true;
        }

        // Add parameters
        if ($openApi->parameters !== []) {
            $operation['parameters'] = array_map(
                fn ($param) => $this->buildParameterSchema($param),
                $openApi->parameters
            );
        }

        // Add request body
        if ($openApi->requestBody !== null) {
            $requestBody = $openApi->requestBody;
            $operation['requestBody'] = [
                'required' => $requestBody->required,
                'content' => [
                    $requestBody->contentType => [
                        'schema' => $requestBody->schema,
                    ],
                ],
            ];

            if ($requestBody->description !== null) {
                $operation['requestBody']['description'] = $requestBody->description;
            }
        }

        // Add responses
        $operation['responses'] = [];
        foreach ($openApi->responses as $statusCode => $response) {
            $responseSchema = ['description' => $response->description];

            if ($response->contentType !== null) {
                $schema = $response->schemaRef !== null
                    ? ['$ref' => $response->schemaRef]
                    : $response->schema;

                $responseSchema['content'] = [
                    $response->contentType => [
                        'schema' => $schema,
                    ],
                ];
            }

            if ($response->headers !== null) {
                $responseSchema['headers'] = array_map(
                    fn ($header) => $this->buildHeaderSchema($header),
                    $response->headers
                );
            }

            $operation['responses'][(string) $statusCode] = $responseSchema;
        }

        // Add security if specified
        if ($openApi->security !== []) {
            $operation['security'] = $openApi->security;
        }

        return [
            $endpoint->path => [
                $endpoint->method => $operation,
            ],
        ];
    }

    /**
     * Build parameter schema from OpenApiParameter.
     *
     * @return array<string, mixed>
     */
    private function buildParameterSchema(\AlexFigures\Symfony\Docs\Attribute\OpenApiParameter $param): array
    {
        $schema = [
            'name' => $param->name,
            'in' => $param->in,
            'required' => $param->required,
        ];

        if ($param->description !== '') {
            $schema['description'] = $param->description;
        }

        if ($param->schema !== null) {
            $schema['schema'] = $param->schema;
        } else {
            $typeSchema = ['type' => $param->type];
            if ($param->format !== null) {
                $typeSchema['format'] = $param->format;
            }

            $schema['schema'] = $typeSchema;
        }

        if ($param->example !== null) {
            $schema['example'] = $param->example;
        }

        return $schema;
    }

    /**
     * Build header schema from OpenApiHeader.
     *
     * @return array<string, mixed>
     */
    private function buildHeaderSchema(\AlexFigures\Symfony\Docs\Attribute\OpenApiHeader $header): array
    {
        $schema = [
            'description' => $header->description,
            'schema' => ['type' => $header->type],
        ];

        if ($header->format !== null) {
            $schema['schema']['format'] = $header->format;
        }

        return $schema;
    }

    /**
     * Generate operation ID from path and method.
     */
    private function generateOperationId(string $path, string $method): string
    {
        // Remove leading slash and convert to camelCase
        $path = ltrim($path, '/');
        $path = str_replace(['/', '-', '_', '{', '}'], ' ', $path);
        $parts = array_filter(explode(' ', $path));
        $parts = array_map('ucfirst', $parts);

        return strtolower($method) . implode('', $parts);
    }

    /**
     * Build query parameters for collection GET operation.
     *
     * Includes pagination, filtering, sorting, sparse fieldsets, and include parameters.
     *
     * @return list<OpenApiSchema>
     */
    private function buildCollectionParameters(ResourceMetadata $metadata): array
    {
        $parameters = [];

        // Pagination parameters
        $parameters[] = [
            'name' => 'page[number]',
            'in' => 'query',
            'description' => 'Page number for pagination',
            'required' => false,
            'schema' => [
                'type' => 'integer',
                'minimum' => 1,
                'default' => 1,
            ],
        ];

        $parameters[] = [
            'name' => 'page[size]',
            'in' => 'query',
            'description' => 'Number of items per page',
            'required' => false,
            'schema' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 100,
                'default' => 20,
            ],
        ];

        // Filter parameters
        if ($metadata->filterableFields !== null) {
            foreach ($metadata->filterableFields->getFields() as $fieldName => $fieldConfig) {
                foreach ($fieldConfig->operators as $operator) {
                    $parameters[] = $this->buildFilterParameter($fieldName, $operator, $fieldConfig);
                }
            }
        }

        // Sort parameter
        if ($metadata->sortableFields !== null) {
            $sortableFieldNames = $metadata->sortableFields->getAllowedFields();
            if ($sortableFieldNames !== []) {
                $parameters[] = [
                    'name' => 'sort',
                    'in' => 'query',
                    'description' => sprintf(
                        'Sort order. Prefix with `-` for descending. Allowed fields: %s',
                        implode(', ', $sortableFieldNames)
                    ),
                    'required' => false,
                    'schema' => [
                        'type' => 'string',
                    ],
                    'example' => '-' . $sortableFieldNames[0],
                ];
            }
        }

        // Sparse fieldsets parameter
        $parameters[] = [
            'name' => 'fields[' . $metadata->type . ']',
            'in' => 'query',
            'description' => 'Comma-separated list of fields to include in the response',
            'required' => false,
            'schema' => [
                'type' => 'string',
            ],
        ];

        // Include parameter (relationships)
        if ($metadata->relationships !== []) {
            $relationshipNames = array_keys($metadata->relationships);
            $parameters[] = [
                'name' => 'include',
                'in' => 'query',
                'description' => sprintf(
                    'Comma-separated list of relationships to include. Available: %s',
                    implode(', ', $relationshipNames)
                ),
                'required' => false,
                'schema' => [
                    'type' => 'string',
                ],
                'example' => $relationshipNames[0],
            ];
        }

        return $parameters;
    }

    /**
     * Build a filter parameter for OpenAPI spec.
     *
     * @return OpenApiSchema
     */
    private function buildFilterParameter(string $fieldName, string $operator, \AlexFigures\Symfony\Resource\Attribute\FilterableField $fieldConfig): array
    {
        $operatorDescriptions = [
            'eq' => 'equals',
            'ne' => 'not equals',
            'gt' => 'greater than',
            'gte' => 'greater than or equal',
            'lt' => 'less than',
            'lte' => 'less than or equal',
            'like' => 'pattern match (use * as wildcard)',
            'in' => 'value in list (comma-separated)',
            'nin' => 'value not in list (comma-separated)',
            'null' => 'is null (use 1 for true, 0 for false)',
            'nnull' => 'is not null (use 1 for true, 0 for false)',
        ];

        $description = sprintf(
            'Filter by %s (%s)',
            $fieldName,
            $operatorDescriptions[$operator] ?? $operator
        );

        if ($fieldConfig->hasCustomHandler()) {
            $description .= ' [custom handler]';
        }

        $schema = [
            'type' => 'string',
        ];

        // Add examples for specific operators
        if ($operator === 'like') {
            $schema['example'] = '*search*';
        } elseif ($operator === 'in' || $operator === 'nin') {
            $schema['example'] = 'value1,value2,value3';
        } elseif ($operator === 'null' || $operator === 'nnull') {
            $schema['example'] = '1';
        }

        return [
            'name' => sprintf('filter[%s][%s]', $fieldName, $operator),
            'in' => 'query',
            'description' => $description,
            'required' => false,
            'schema' => $schema,
        ];
    }
}
