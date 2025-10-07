<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Resource\Attribute;

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
     */
    public function isAllowed(string $field): bool
    {
        return isset($this->fields[$field]);
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
     */
    public function isOperatorAllowed(string $field, string $operator): bool
    {
        $config = $this->getFieldConfig($field);
        return $config?->isOperatorAllowed($operator) ?? false;
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
     * Normalize the input fields array to a consistent format.
     *
     * @param list<FilterableField|string> $fields
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
