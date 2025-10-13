<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Resource\Metadata;

use AlexFigures\Symfony\Resource\Attribute\FilterableFields;

/**
 * @psalm-type AttributeMap = array<string, AttributeMetadata>
 * @psalm-type RelationshipMap = array<string, RelationshipMetadata>
 */
final class ResourceMetadata
{
    /**
     * @param AttributeMap                 $attributes
     * @param RelationshipMap              $relationships
     * @param class-string                 $class
     * @param list<string>                 $sortableFields
     * @param array<string, mixed>         $normalizationContext
     * @param array<string, mixed>         $denormalizationContext
     * @param array<string, class-string>  $dtoClasses
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
        public array $sortableFields = [],
        public ?FilterableFields $filterableFields = null,
        public ?OperationGroups $operationGroups = null,
        public array $normalizationContext = [],
        public array $denormalizationContext = [],
        public array $dtoClasses = [],
    ) {
    }

    /**
     * Returns operation groups for this resource.
     *
     * @deprecated Use normalizationContext and denormalizationContext instead
     */
    public function getOperationGroups(): OperationGroups
    {
        return $this->operationGroups ?? OperationGroups::default();
    }

    /**
     * Get serialization groups for reading.
     *
     * @return list<string>
     */
    public function getNormalizationGroups(): array
    {
        return $this->normalizationContext['groups'] ?? [];
    }

    /**
     * Get serialization groups for writing.
     * Same groups are used for both create and update operations.
     *
     * @return list<string>
     */
    public function getDenormalizationGroups(): array
    {
        $groups = $this->denormalizationContext['groups'] ?? [];

        // Always add Default group for Symfony validation compatibility
        if (!in_array('Default', $groups, true)) {
            $groups[] = 'Default';
        }

        return $groups;
    }

    /**
     * Resolve the DTO class for the provided API version.
     *
     * @param non-empty-string|null $version
     *
     * @return class-string|null
     */
    public function getDtoClass(?string $version): ?string
    {
        if ($version === null || $version === '') {
            return null;
        }

        return $this->dtoClasses[$version] ?? null;
    }
}
