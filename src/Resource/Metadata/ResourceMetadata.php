<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Resource\Metadata;

/**
 * @psalm-type AttributeMap = array<string, AttributeMetadata>
 * @psalm-type RelationshipMap = array<string, RelationshipMetadata>
 */
final class ResourceMetadata
{
    /**
     * @param AttributeMap    $attributes
     * @param RelationshipMap $relationships
     * @param class-string    $class
     */
    public function __construct(
        public string $type,
        public string $class,
        public array $attributes,
        public array $relationships,
        public bool $exposeId = true,
        public ?string $idPropertyPath = null,
        public ?string $routePrefix = null,
        public ?string $description = null,
    ) {
    }
}
