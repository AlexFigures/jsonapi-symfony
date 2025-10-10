<?php

declare(strict_types=1);

namespace JsonApi\Symfony\CustomRoute\Query;

use Doctrine\ORM\QueryBuilder;
use JsonApi\Symfony\Filter\Ast\Comparison;
use JsonApi\Symfony\Filter\Ast\Conjunction;
use JsonApi\Symfony\Filter\Ast\Node;
use JsonApi\Symfony\Query\Criteria;

/**
 * Fluent builder for modifying Criteria with custom filters and conditions.
 *
 * This builder allows custom route handlers to add filters and conditions
 * to the already-parsed JSON:API query parameters without manually working
 * with the Filter AST.
 *
 * Example usage:
 * ```php
 * $criteria = $context->criteria()
 *     ->addFilter('category.id', 'eq', $categoryId)
 *     ->addFilter('status', 'eq', 'published')
 *     ->build();
 * ```
 *
 * For complex conditions that can't be expressed as simple filters:
 * ```php
 * $criteria = $context->criteria()
 *     ->addCustomCondition(function(QueryBuilder $qb) use ($categoryId) {
 *         $qb->andWhere('e.category = :categoryId')
 *            ->setParameter('categoryId', $categoryId);
 *     })
 *     ->build();
 * ```
 *
 * @api This class is part of the public API and follows semantic versioning.
 * @since 0.3.0
 */
final class CriteriaBuilder
{
    /**
     * @var list<Node> Additional filter nodes to merge with existing filters
     */
    private array $additionalFilters = [];

    /**
     * @var list<callable(QueryBuilder): void> Custom QueryBuilder modifiers
     */
    private array $customConditions = [];

    /**
     * @param Criteria $baseCriteria The base criteria parsed from query string
     */
    public function __construct(
        private readonly Criteria $baseCriteria,
    ) {
    }

    /**
     * Add a simple filter condition.
     *
     * This is a convenience method for adding filters without manually
     * constructing Filter AST nodes. The filter will be combined with
     * existing filters using AND logic.
     *
     * Supported operators:
     * - 'eq' - equals
     * - 'ne' - not equals
     * - 'lt' - less than
     * - 'lte' - less than or equal
     * - 'gt' - greater than
     * - 'gte' - greater than or equal
     * - 'in' - in array
     * - 'nin' - not in array
     * - 'like' - SQL LIKE pattern
     * - 'ilike' - case-insensitive LIKE
     *
     * Example:
     * ```php
     * $builder->addFilter('category.id', 'eq', 123);
     * $builder->addFilter('status', 'in', ['published', 'draft']);
     * $builder->addFilter('title', 'like', '%search%');
     * ```
     *
     * @param string $field    The field path (e.g., 'name', 'category.id')
     * @param string $operator The comparison operator
     * @param mixed  $value    The value(s) to compare against
     *
     * @return self For method chaining
     */
    public function addFilter(string $field, string $operator, mixed $value): self
    {
        // Normalize value to list for Comparison node
        $values = is_array($value) ? array_values($value) : [$value];

        $this->additionalFilters[] = new Comparison(
            fieldPath: $field,
            operator: $operator,
            values: $values,
        );

        return $this;
    }

    /**
     * Add a custom condition using a QueryBuilder modifier callback.
     *
     * Use this for complex conditions that can't be expressed as simple filters,
     * such as:
     * - Subqueries
     * - Complex joins
     * - Database-specific functions
     * - OR conditions across multiple fields
     *
     * The callback receives a QueryBuilder instance and should modify it
     * in place. Multiple custom conditions are applied in the order they
     * were added.
     *
     * Example:
     * ```php
     * $builder->addCustomCondition(function(QueryBuilder $qb) {
     *     $qb->andWhere('e.publishedAt IS NOT NULL')
     *        ->andWhere('e.publishedAt <= :now')
     *        ->setParameter('now', new \DateTimeImmutable());
     * });
     * ```
     *
     * @param callable(QueryBuilder): void $modifier Callback to modify the QueryBuilder
     *
     * @return self For method chaining
     */
    public function addCustomCondition(callable $modifier): self
    {
        $this->customConditions[] = $modifier;

        return $this;
    }

    /**
     * Build the final Criteria with all modifications applied.
     *
     * This method:
     * 1. Clones the base criteria to avoid mutations
     * 2. Merges additional filters with existing filters using AND logic
     * 3. Stores custom conditions for later application by the repository
     *
     * @return Criteria The modified criteria ready for use with repository
     */
    public function build(): Criteria
    {
        // Clone the base criteria to avoid mutations
        $criteria = clone $this->baseCriteria;

        // Merge additional filters with existing filters
        if ($this->additionalFilters !== []) {
            $criteria->filter = $this->mergeFilters(
                $criteria->filter,
                $this->additionalFilters
            );
        }

        // Store custom conditions in criteria for repository to apply
        if ($this->customConditions !== []) {
            $criteria->customConditions = array_merge(
                $criteria->customConditions,
                $this->customConditions
            );
        }

        return $criteria;
    }

    /**
     * Merge additional filters with existing filters using AND logic.
     *
     * @param Node|null   $existingFilter The existing filter AST (may be null)
     * @param list<Node>  $newFilters     New filter nodes to add
     *
     * @return Node The merged filter AST
     */
    private function mergeFilters(?Node $existingFilter, array $newFilters): Node
    {
        if ($newFilters === []) {
            return $existingFilter ?? new Conjunction([]);
        }

        // If there's no existing filter, just wrap new filters in Conjunction
        if ($existingFilter === null) {
            return count($newFilters) === 1
                ? $newFilters[0]
                : new Conjunction($newFilters);
        }

        // If existing filter is already a Conjunction, add to it
        if ($existingFilter instanceof Conjunction) {
            return new Conjunction([...$existingFilter->children, ...$newFilters]);
        }

        // Otherwise, create a new Conjunction with both
        return new Conjunction([$existingFilter, ...$newFilters]);
    }
}

