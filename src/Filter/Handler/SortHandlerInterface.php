<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Filter\Handler;

/**
 * Interface for custom sort handlers.
 *
 * Custom sort handlers allow you to implement complex sorting logic
 * that goes beyond simple field ordering. This is useful for:
 * - Relevance-based sorting (e.g., search results)
 * - Custom business logic sorting
 * - Multi-field composite sorting
 * - Geospatial distance sorting
 *
 * Example implementation:
 * ```php
 * final class RelevanceSortHandler implements SortHandlerInterface
 * {
 *     public function supports(string $field): bool
 *     {
 *         return $field === 'relevance';
 *     }
 *
 *     public function handle(string $field, bool $descending, object $queryBuilder): void
 *     {
 *         // Implement relevance-based sorting
 *         $queryBuilder->addOrderBy('MATCH(title, content) AGAINST (:search)', $descending ? 'DESC' : 'ASC');
 *     }
 * }
 * ```
 *
 * @api This interface is part of the public API and follows semantic versioning.
 * @since 1.1.0
 */
interface SortHandlerInterface
{
    /**
     * Check if this handler supports the given field.
     *
     * @param  string $field The field name being sorted
     * @return bool   True if this handler can process the sort
     */
    public function supports(string $field): bool;

    /**
     * Handle the sort by modifying the query builder.
     *
     * @param  string $field        The field name being sorted
     * @param  bool   $descending   True for descending order, false for ascending
     * @param  object $queryBuilder The query builder to modify (typically Doctrine QueryBuilder)
     * @return void
     */
    public function handle(string $field, bool $descending, object $queryBuilder): void;

    /**
     * Get the priority of this handler.
     *
     * Handlers with higher priority will be checked first.
     * Default priority is 0.
     *
     * @return int The priority (higher = more important)
     */
    public function getPriority(): int;
}
