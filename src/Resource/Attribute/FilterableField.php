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
     * @param string             $field         Field name that can be filtered
     * @param array<int, string> $operators     List of allowed operators for this field (default: all operators)
     * @param string|null        $customHandler Optional service ID for custom filter handler
     */
    public function __construct(
        public readonly string $field,
        array $operators = [
            'eq', 'ne', 'gt', 'gte', 'lt', 'lte',
            'like', 'in', 'nin', 'null', 'nnull'
        ],
        public readonly ?string $customHandler = null,
    ) {
        $this->operators = array_values($operators);
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
}
