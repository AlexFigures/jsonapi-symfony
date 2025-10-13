<?php

declare(strict_types=1);

namespace App\Sort;

use Doctrine\ORM\QueryBuilder;
use AlexFigures\Symfony\Filter\Handler\SortHandlerInterface;

/**
 * Example custom sort handler for relevance-based sorting.
 *
 * This handler demonstrates how to implement complex sorting logic
 * that combines multiple factors to calculate relevance.
 *
 * Usage in resource:
 * ```php
 * #[JsonApiResource(type: 'articles')]
 * #[SortableFields(['title', 'createdAt', 'relevance'])]
 * class Article {}
 * ```
 *
 * API request:
 * GET /api/articles?sort=-relevance
 */
final class RelevanceSorter implements SortHandlerInterface
{
    public function supports(string $field): bool
    {
        return $field === 'relevance';
    }

    public function handle(string $field, bool $descending, object $queryBuilder): void
    {
        if (!$queryBuilder instanceof QueryBuilder) {
            throw new \InvalidArgumentException('Expected Doctrine QueryBuilder');
        }

        $rootAliases = $queryBuilder->getRootAliases();
        $rootAlias = $rootAliases[0] ?? 'e';

        $direction = $descending ? 'DESC' : 'ASC';

        // Calculate relevance score based on:
        // - View count (70% weight)
        // - Recency (30% weight, newer articles get higher score)
        $relevanceFormula = sprintf(
            '(%s.viewCount * 0.7 + (DATEDIFF(CURRENT_DATE(), %s.createdAt) * -0.3))',
            $rootAlias,
            $rootAlias
        );

        $queryBuilder->addSelect("($relevanceFormula) AS HIDDEN relevance_score");
        $queryBuilder->addOrderBy('relevance_score', $direction);
    }

    public function getPriority(): int
    {
        return 0;
    }
}
