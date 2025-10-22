<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Resource\Attribute;

/**
 * Defines configuration for a single filterable field.
 *
 * This class represents the configuration for one field that can be used
 * in JSON:API filter queries. It specifies which operators are allowed
 * and optionally a custom handler for processing the filter.
 *
 * Example usage:
 * ```php
 * new FilterableField(
 *     field: 'title',
 *     operators: ['eq', 'ne', 'like'],
 *     customHandler: 'app.filter.title_search'
 * )
 * ```
 *
 * **Filter Inheritance**:
 * When a relationship field is marked with `inherit: true`, all filters from
 * the related resource are automatically inherited:
 * ```php
 * // Author resource
 * #[FilterableFields([
 *     new FilterableField('name', operators: ['eq', 'like']),
 *     new FilterableField('email', operators: ['eq']),
 * ])]
 * class Author { }
 *
 * // Article resource
 * #[FilterableFields([
 *     'title',
 *     new FilterableField('author', inherit: true), // Inherits name, email
 * ])]
 * class Article { }
 *
 * // Allows: filter[author.name][like]=John, filter[author.email][eq]=john@example.com
 * ```
 *
 * You can exclude specific fields from inheritance:
 * ```php
 * new FilterableField('author', inherit: true, except: ['email'])
 * ```
 *
 * **Supported Operators**:
 * - `eq`: Equals (=)
 * - `ne`: Not equals (!=)
 * - `gt`: Greater than (>)
 * - `gte`: Greater than or equal (>=)
 * - `lt`: Less than (<)
 * - `lte`: Less than or equal (<=)
 * - `like`: SQL LIKE pattern matching
 * - `in`: Value in list
 * - `nin`: Value not in list
 * - `null`: Is null
 * - `nnull`: Is not null
 *
 * **Custom Handlers**:
 * Custom handlers allow you to implement complex filtering logic that goes
 * beyond simple field comparisons. The handler should be a service ID that
 * implements FilterHandlerInterface.
 *
 * @api This class is part of the public API and follows semantic versioning.
 * @since 0.1.0
 */
final class FilterableField
{
    /**
     * @var list<string>
     */
    public readonly array $operators;

    /**
     * @var list<string>
     */
    public readonly array $except;

    /**
     * @param string             $field         Field name that can be filtered
     * @param array<int, string> $operators     List of allowed operators for this field (default: all operators)
     * @param string|null        $customHandler Optional service ID for custom filter handler
     * @param bool               $inherit       Whether to inherit filters from related resource (for relationship fields)
     * @param array<int, string> $except        List of fields to exclude from inheritance
     */
    public function __construct(
        public readonly string $field,
        array $operators = [
            'eq', 'ne', 'gt', 'gte', 'lt', 'lte',
            'like', 'in', 'nin', 'null', 'nnull'
        ],
        public readonly ?string $customHandler = null,
        public readonly bool $inherit = false,
        array $except = [],
    ) {
        $this->operators = array_values($operators);
        $this->except = array_values($except);
    }

    /**
     * Check if a specific operator is allowed for this field.
     */
    public function isOperatorAllowed(string $operator): bool
    {
        return in_array($operator, $this->operators, true);
    }

    /**
     * Check if this field has a custom handler.
     */
    public function hasCustomHandler(): bool
    {
        return $this->customHandler !== null;
    }

    /**
     * Check if this field should inherit filters from related resource.
     */
    public function shouldInherit(): bool
    {
        return $this->inherit;
    }

    /**
     * Check if a specific field is excluded from inheritance.
     */
    public function isExcluded(string $field): bool
    {
        return in_array($field, $this->except, true);
    }
}
