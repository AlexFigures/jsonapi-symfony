<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Integration\Fixtures\CustomRoute;

use Doctrine\ORM\EntityManagerInterface;
use JsonApi\Symfony\CustomRoute\Attribute\NoTransaction;
use JsonApi\Symfony\CustomRoute\Context\CustomRouteContext;
use JsonApi\Symfony\CustomRoute\Handler\CustomRouteHandlerInterface;
use JsonApi\Symfony\CustomRoute\Result\CustomRouteResult;

/**
 * Test handler for fetching category synonyms by category ID.
 *
 * This is a read-only operation, so we use #[NoTransaction] for better performance.
 *
 * This handler demonstrates the Hybrid Pattern for custom routes:
 * - Uses CriteriaBuilder to add custom filter for categoryId
 * - Leverages ResourceRepository for automatic filter/sort/pagination
 * - All JSON:API query parameters (filter, sort, page) are automatically applied
 */
#[NoTransaction]
final class CategorySynonymsByCategoryHandler implements CustomRouteHandlerInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em
    ) {
    }

    public function handle(CustomRouteContext $context): CustomRouteResult
    {
        $categoryId = $context->getParam('categoryId');

        // Verify category exists
        $category = $this->em->getRepository(\JsonApi\Symfony\Tests\Integration\Fixtures\Entity\Category::class)
            ->find($categoryId);

        if ($category === null) {
            return CustomRouteResult::notFound('Category not found');
        }

        // Build criteria with custom condition for categoryId
        // This merges with any filters/sorting/pagination from query string
        // We use addCustomCondition because filtering by association requires special handling
        $criteria = $context->criteria()
            ->addCustomCondition(function ($qb) use ($categoryId) {
                // Filter by category using the association
                $qb->andWhere('e.category = :categoryId')
                   ->setParameter('categoryId', $categoryId);
            })
            ->build();

        // Use repository to fetch collection with all criteria applied
        // This automatically handles: filters, sorting, pagination, includes
        $slice = $context->getRepository()->findCollection('category_synonyms', $criteria);

        return CustomRouteResult::collection($slice->items, $slice->totalItems);
    }
}
