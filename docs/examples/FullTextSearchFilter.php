<?php

declare(strict_types=1);

namespace App\Filter;

use Doctrine\ORM\QueryBuilder;
use JsonApi\Symfony\Filter\Ast\Comparison;
use JsonApi\Symfony\Filter\Handler\FilterHandlerInterface;

/**
 * Example custom filter handler for full-text search.
 *
 * This handler demonstrates how to implement a custom filter that searches
 * across multiple fields using a single "search" parameter.
 *
 * Usage in resource:
 * ```php
 * #[JsonApiResource(type: 'articles')]
 * #[FilterableFields([
 *     new FilterableField('search', customHandler: FullTextSearchFilter::class),
 *     new FilterableField('status', operators: ['eq']),
 * ])]
 * class Article {}
 * ```
 *
 * API request:
 * GET /api/articles?filter[search][eq]=symfony
 */
final class FullTextSearchFilter implements FilterHandlerInterface
{
    public function supports(string $field, string $operator): bool
    {
        return $field === 'search' && $operator === 'eq';
    }

    public function handle(string $field, string $operator, array $values, object $queryBuilder): void
    {
        if (!$queryBuilder instanceof QueryBuilder) {
            throw new \InvalidArgumentException('Expected Doctrine QueryBuilder');
        }

        $searchTerm = $values[0] ?? '';
        if ($searchTerm === '') {
            return;
        }

        $rootAliases = $queryBuilder->getRootAliases();
        $rootAlias = $rootAliases[0] ?? 'e';

        // Search across title and content fields
        $queryBuilder->andWhere(
            $queryBuilder->expr()->orX(
                $queryBuilder->expr()->like("$rootAlias.title", ':searchTerm'),
                $queryBuilder->expr()->like("$rootAlias.content", ':searchTerm'),
                $queryBuilder->expr()->like("$rootAlias.summary", ':searchTerm')
            )
        );

        $queryBuilder->setParameter('searchTerm', '%' . $searchTerm . '%');
    }

    public function getPriority(): int
    {
        return 0;
    }
}
