<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Fixtures\CustomRoute;

use JsonApi\Symfony\CustomRoute\Attribute\NoTransaction;
use JsonApi\Symfony\CustomRoute\Context\CustomRouteContext;
use JsonApi\Symfony\CustomRoute\Handler\CustomRouteHandlerInterface;
use JsonApi\Symfony\CustomRoute\Result\CustomRouteResult;

/**
 * Test handler for searching articles (read-only, no transaction).
 */
#[NoTransaction]
final class SearchArticlesHandler implements CustomRouteHandlerInterface
{
    public function __construct(
        private array $articles = []
    ) {}

    public function handle(CustomRouteContext $context): CustomRouteResult
    {
        $query = $context->getQueryParam('q');

        if ($query === null || $query === '') {
            return CustomRouteResult::badRequest('Query parameter "q" is required');
        }

        // Simple search implementation
        $results = array_filter(
            $this->articles,
            fn($article) => str_contains(strtolower($article->title), strtolower($query))
        );

        return CustomRouteResult::collection(array_values($results))
            ->withMeta([
                'query' => $query,
                'resultCount' => count($results),
            ]);
    }
}

