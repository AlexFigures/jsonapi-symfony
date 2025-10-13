<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Filter\Handler;

use AlexFigures\Symfony\Filter\Ast\Node;

/**
 * Interface for custom filter handlers.
 *
 * Custom filter handlers allow you to implement complex filtering logic
 * that goes beyond simple field comparisons. This is useful for:
 * - Full-text search
 * - Geospatial queries
 * - Complex business logic filters
 * - Cross-field validations
 *
 * Example implementation:
 * ```php
 * final class FullTextSearchHandler implements FilterHandlerInterface
 * {
 *     public function supports(string $field, string $operator): bool
 *     {
 *         return $field === 'content' && $operator === 'search';
 *     }
 *
 *     public function handle(string $field, string $operator, array $values, object $queryBuilder): void
 *     {
 *         // Implement full-text search logic
 *         $queryBuilder->andWhere('MATCH(content) AGAINST (:search IN BOOLEAN MODE)')
 *                      ->setParameter('search', $values[0]);
 *     }
 * }
 * ```
 *
 * @api This interface is part of the public API and follows semantic versioning.
 * @since 1.1.0
 */
interface FilterHandlerInterface
{
    /**
     * Check if this handler supports the given field and operator combination.
     *
     * @param  string $field    The field name being filtered
     * @param  string $operator The operator being used
     * @return bool   True if this handler can process the filter
     */
    public function supports(string $field, string $operator): bool;

    /**
     * Handle the filter by modifying the query builder.
     *
     * @param  string      $field        The field name being filtered
     * @param  string      $operator     The operator being used
     * @param  list<mixed> $values       The filter values
     * @param  object      $queryBuilder The query builder to modify (typically Doctrine QueryBuilder)
     * @return void
     */
    public function handle(string $field, string $operator, array $values, object $queryBuilder): void;

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
