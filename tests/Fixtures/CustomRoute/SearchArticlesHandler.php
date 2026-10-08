<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Fixtures\CustomRoute;

use AlexFigures\JsonApi\CustomRoute\Attribute\NoTransaction;
use AlexFigures\JsonApi\CustomRoute\Context\CustomRouteContext;
use AlexFigures\JsonApi\CustomRoute\Handler\CustomRouteHandlerInterface;
use AlexFigures\JsonApi\CustomRoute\Result\CustomRouteResult;

/**
 * Test handler for searching articles (read-only, no transaction).
 */
#[NoTransaction]
final readonly class SearchArticlesHandler implements CustomRouteHandlerInterface
{
    public function __construct(
        private array $articles = []
    ) {
    }

    public function handle(CustomRouteContext $context): CustomRouteResult
    {
        $query = $context->getQueryParam('q');

        if ($query === null || $query === '') {
            return CustomRouteResult::badRequest('Query parameter "q" is required');
        }

        // Simple search implementation
        $results = array_filter(
            $this->articles,
            fn ($article) => str_contains(strtolower($article->title), strtolower($query))
        );

        return CustomRouteResult::collection(array_values($results))
            ->withMeta([
                'query' => $query,
                'resultCount' => count($results),
            ]);
    }
}
