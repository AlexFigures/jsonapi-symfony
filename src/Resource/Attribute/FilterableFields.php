<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Resource\Attribute;

use Attribute;

/**
 * Defines which fields are allowed for filtering in JSON:API requests.
 *
 * This attribute specifies a whitelist of fields that can be used in the `filter`
 * query parameter. Only fields listed here will be accepted; attempts to filter
 * by other fields will result in a 400 Bad Request error.
 *
 * Example usage:
 * ```php
 * #[JsonApiResource(type: 'articles')]
 * #[FilterableFields([
 *     new FilterableField('title', ['eq', 'like']),
 *     new FilterableField('status', ['eq', 'in']),
 *     new FilterableField('createdAt', ['gt', 'gte', 'lt', 'lte']),
 *     new FilterableField('content', customHandler: 'app.filter.fulltext_search'),
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
 *     public string $status;
 *
 *     #[Attribute]
 *     #[SerializationGroups(['read'])]
 *     public \DateTimeImmutable $createdAt;
 *
 *     #[Attribute]
 *     public string $content;
 * }
 * ```
 *
 * **Simplified syntax** for basic filtering (all operators allowed):
 * ```php
 * #[FilterableFields(['title', 'status', 'createdAt'])]
 * ```
 *
 * **Security Note**: Always use a whitelist approach for filtering to prevent:
 * - Information disclosure through timing attacks
 * - Performance issues from filtering on unindexed columns
 * - Exposure of internal field names
 * - SQL injection attacks
 *
 * **Request Example**:
 * ```
 * GET /api/articles?filter[title][like]=*search*&filter[status][eq]=published
 * ```
 *
 * @api This attribute is part of the public API and follows semantic versioning.
 * @since 1.1.0
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class FilterableFields
{
    /**
     * @var array<string, FilterableField>
     */
    public readonly array $fields;

    /**
     * @param list<FilterableField|string> $fields List of filterable field configurations or field names
     */
    public function __construct(array $fields)
    {
        $this->fields = $this->normalizeFields($fields);
    }

    /**
     * Check if a field is allowed for filtering.
     *
     * This method checks both directly declared fields and inherited fields
     * from related resources (when inherit=true is set on a relationship field).
     *
     * @param string                                                      $field    Field path (e.g., 'title' or 'author.name')
     * @param \AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface|null $registry Optional registry for inheritance resolution
     * @param string|null                                                 $type     Resource type for inheritance resolution
     * @param int                                                         $depth    Current inheritance depth (for cycle prevention)
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
    public function getFieldConfig(string $field): ?FilterableField
    {
        return $this->fields[$field] ?? null;
    }

    /**
     * Check if a specific operator is allowed for a field.
     *
     * This method checks both directly declared fields and inherited fields
     * from related resources (when inherit=true is set on a relationship field).
     *
     * @param string                                                      $field    Field path (e.g., 'title' or 'author.name')
     * @param string                                                      $operator Operator to check
     * @param \AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface|null $registry Optional registry for inheritance resolution
     * @param string|null                                                 $type     Resource type for inheritance resolution
     * @param int                                                         $depth    Current inheritance depth (for cycle prevention)
     */
    public function isOperatorAllowed(
        string $field,
        string $operator,
        ?\AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface $registry = null,
        ?string $type = null,
        int $depth = 0
    ): bool {
        // Check direct field configuration first
        $config = $this->getFieldConfig($field);
        if ($config !== null) {
            return $config->isOperatorAllowed($operator);
        }

        // Check inherited fields if registry and type are provided
        if ($registry !== null && $type !== null) {
            return $this->isOperatorAllowedForInheritedField($field, $operator, $registry, $type, $depth);
        }

        return false;
    }

    /**
     * Get all field configurations.
     *
     * @return array<string, FilterableField>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * Check if an operator is allowed for an inherited field.
     *
     * @param string                                                   $field    Field path (e.g., 'author.name')
     * @param string                                                   $operator Operator to check
     * @param \AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface $registry Resource registry
     * @param string                                                   $type     Current resource type
     * @param int                                                      $depth    Current inheritance depth
     */
    private function isOperatorAllowedForInheritedField(
        string $field,
        string $operator,
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
        $relatedFilterableFields = $relatedMetadata->filterableFields;

        if ($relatedFilterableFields === null) {
            return false;
        }

        // Recursively check if the operator is allowed in the related resource
        return $relatedFilterableFields->isOperatorAllowed(
            $relatedField,
            $operator,
            $registry,
            $relationship->targetType,
            $depth + 1
        );
    }

    /**
     * Check if a field is allowed through inheritance from a related resource.
     *
     * @param string                                                   $field    Field path (e.g., 'author.name')
     * @param \AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface $registry Resource registry
     * @param string                                                   $type     Current resource type
     * @param int                                                      $depth    Current inheritance depth
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
        $relatedFilterableFields = $relatedMetadata->filterableFields;

        if ($relatedFilterableFields === null) {
            return false;
        }

        // Recursively check if the field is allowed in the related resource
        return $relatedFilterableFields->isAllowed(
            $relatedField,
            $registry,
            $relationship->targetType,
            $depth + 1
        );
    }

    /**
     * Normalize the input fields array to a consistent format.
     *
     * @param  list<FilterableField|string>   $fields
     * @return array<string, FilterableField>
     */
    private function normalizeFields(array $fields): array
    {
        $normalized = [];

        foreach ($fields as $field) {
            if (is_string($field)) {
                // Simple string field name - create FilterableField with all operators
                $filterableField = new FilterableField($field);
            } elseif ($field instanceof FilterableField) {
                $filterableField = $field;
            } else {
                throw new \InvalidArgumentException(sprintf(
                    'FilterableFields expects array of FilterableField instances or strings, got %s',
                    get_debug_type($field)
                ));
            }

            $normalized[$filterableField->field] = $filterableField;
        }

        return $normalized;
    }
}
