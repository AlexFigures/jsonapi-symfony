<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Resource\Attribute;

use Attribute;

/**
 * Defines which fields are allowed for sorting in JSON:API requests.
 *
 * This attribute specifies a whitelist of fields that can be used in the `sort`
 * query parameter. Only fields listed here will be accepted; attempts to sort
 * by other fields will result in a 400 Bad Request error.
 *
 * Example usage:
 * ```php
 * #[JsonApiResource(type: 'articles')]
 * #[SortableFields([
 *     new SortableField('title'),
 *     new SortableField('createdAt'),
 *     new SortableField('updatedAt'),
 *     new SortableField('viewCount'),
 * ])]
 * final class Article
 * {
 *     #[Id]
 *     #[Attribute]
 *     public string $id;
 *
 *     #[Attribute]
 *     public string $title;
 *
 *     #[Attribute]
 *     #[SerializationGroups(['read'])]
 *     public \DateTimeImmutable $createdAt;
 *
 *     #[Attribute]
 *     #[SerializationGroups(['read'])]
 *     public \DateTimeImmutable $updatedAt;
 *
 *     #[Attribute]
 *     public int $viewCount;
 * }
 * ```
 *
 * **Simplified syntax** for basic sorting:
 * ```php
 * #[SortableFields(['title', 'createdAt', 'updatedAt'])]
 * ```
 *
 * **Sort Inheritance**:
 * When a relationship field is marked with `inherit: true`, all sortable fields from
 * the related resource are automatically inherited:
 * ```php
 * // Author resource
 * #[SortableFields([
 *     new SortableField('name'),
 *     new SortableField('email'),
 * ])]
 * class Author { }
 *
 * // Article resource
 * #[SortableFields([
 *     'title',
 *     new SortableField('author', inherit: true), // Inherits name, email
 * ])]
 * class Article { }
 *
 * // Allows: sort=author.name, sort=-author.email
 * ```
 *
 * You can exclude specific fields from inheritance:
 * ```php
 * new SortableField('author', inherit: true, except: ['email'])
 * ```
 *
 * **Security Note**: Always use a whitelist approach for sorting to prevent:
 * - Information disclosure through timing attacks
 * - Performance issues from sorting on unindexed columns
 * - Exposure of internal field names
 *
 * **Request Example**:
 * ```
 * GET /api/articles?sort=-createdAt,title
 * ```
 * This sorts by `createdAt` descending, then by `title` ascending.
 *
 * @api This attribute is part of the public API and follows semantic versioning.
 * @since 1.0.0
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class SortableFields
{
    /**
     * @var array<string, SortableField>
     */
    public readonly array $fields;

    /**
     * @param list<SortableField|string> $fields List of sortable field configurations or field names
     */
    public function __construct(array $fields)
    {
        $this->fields = $this->normalizeFields($fields);
    }

    /**
     * Check if a field is allowed for sorting.
     *
     * This method checks both directly declared fields and inherited fields
     * from related resources (when inherit=true is set on a relationship field).
     *
     * @param string                                                                $field    Field path (e.g., 'title' or 'author.name')
     * @param \AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface|null $registry Optional registry for inheritance resolution
     * @param string|null                                                           $type     Resource type for inheritance resolution
     * @param int                                                                   $depth    Current inheritance depth (for cycle prevention)
     */
    public function isAllowed(
        string $field,
        ?\AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface $registry = null,
        ?string $type = null,
        int $depth = 0
    ): bool {
        // Check direct fields first (priority)
        if (isset($this->fields[$field])) {
            return true;
        }

        // Check inherited fields if registry and type are provided
        if ($registry !== null && $type !== null) {
            return $this->isInheritedFieldAllowed($field, $registry, $type, $depth);
        }

        return false;
    }

    /**
     * Get all allowed field names.
     *
     * @return list<string>
     */
    public function getAllowedFields(): array
    {
        return array_keys($this->fields);
    }

    /**
     * Get configuration for a specific field.
     */
    public function getFieldConfig(string $field): ?SortableField
    {
        return $this->fields[$field] ?? null;
    }

    /**
     * Get all field configurations.
     *
     * @return array<string, SortableField>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * Check if a field is allowed through inheritance from a related resource.
     *
     * @param string                                                           $field    Field path (e.g., 'author.name')
     * @param \AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface $registry Resource registry
     * @param string                                                           $type     Current resource type
     * @param int                                                              $depth    Current inheritance depth
     */
    private function isInheritedFieldAllowed(
        string $field,
        \AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface $registry,
        string $type,
        int $depth
    ): bool {
        // Prevent infinite recursion in circular relationships
        if ($depth >= 2) {
            return false;
        }

        // Only check inheritance for dotted paths (e.g., 'author.name')
        if (!str_contains($field, '.')) {
            return false;
        }

        // Split the field path into relationship and remaining path
        $segments = explode('.', $field, 2);
        $relationshipName = $segments[0];
        $relatedField = $segments[1];

        // Check if the relationship field is configured for inheritance
        $relationshipConfig = $this->getFieldConfig($relationshipName);
        if ($relationshipConfig === null || !$relationshipConfig->shouldInherit()) {
            return false;
        }

        // Check if the field is excluded from inheritance
        if ($relationshipConfig->isExcluded($relatedField)) {
            return false;
        }

        // Get metadata for the current resource
        if (!$registry->hasType($type)) {
            return false;
        }

        $metadata = $registry->getByType($type);
        $relationship = $metadata->relationships[$relationshipName] ?? null;

        if ($relationship === null || $relationship->targetType === null) {
            return false;
        }

        // Get metadata for the related resource
        if (!$registry->hasType($relationship->targetType)) {
            return false;
        }

        $relatedMetadata = $registry->getByType($relationship->targetType);
        $relatedSortableFields = $relatedMetadata->sortableFields;

        if ($relatedSortableFields === null) {
            return false;
        }

        // Recursively check if the field is allowed in the related resource
        return $relatedSortableFields->isAllowed(
            $relatedField,
            $registry,
            $relationship->targetType,
            $depth + 1
        );
    }

    /**
     * Normalize the input fields array to a consistent format.
     *
     * @param  list<SortableField|string>   $fields
     * @return array<string, SortableField>
     */
    private function normalizeFields(array $fields): array
    {
        $normalized = [];

        foreach ($fields as $field) {
            if (is_string($field)) {
                // Simple string field name - create SortableField
                $sortableField = new SortableField($field);
            } elseif ($field instanceof SortableField) {
                $sortableField = $field;
            } else {
                throw new \InvalidArgumentException(sprintf(
                    'SortableFields expects array of SortableField instances or strings, got %s',
                    get_debug_type($field)
                ));
            }

            $normalized[$sortableField->field] = $sortableField;
        }

        return $normalized;
    }
}
