<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Resource\Attribute;

/**
 * Defines configuration for a single sortable field.
 *
 * This class represents the configuration for one field that can be used
 * in JSON:API sort queries. It specifies whether the field allows sorting
 * and optionally a custom handler for processing the sort.
 *
 * Example usage:
 * ```php
 * new SortableField(
 *     field: 'title',
 *     customHandler: 'app.sort.relevance'
 * )
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
 * **Custom Handlers**:
 * Custom handlers allow you to implement complex sorting logic that goes
 * beyond simple field ordering. The handler should be a service ID that
 * implements SortHandlerInterface.
 *
 * @api This class is part of the public API and follows semantic versioning.
 * @since 0.1.0
 */
final class SortableField
{
    /**
     * @var list<string>
     */
    public readonly array $except;

    /**
     * @param string             $field         Field name that can be sorted
     * @param string|null        $customHandler Optional service ID for custom sort handler
     * @param bool               $inherit       Whether to inherit sortable fields from related resource (for relationship fields)
     * @param array<int, string> $except        List of fields to exclude from inheritance
     */
    public function __construct(
        public readonly string $field,
        public readonly ?string $customHandler = null,
        public readonly bool $inherit = false,
        array $except = [],
    ) {
        $this->except = array_values($except);
    }

    /**
     * Check if this field has a custom handler.
     */
    public function hasCustomHandler(): bool
    {
        return $this->customHandler !== null;
    }

    /**
     * Check if this field should inherit sortable fields from related resource.
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
