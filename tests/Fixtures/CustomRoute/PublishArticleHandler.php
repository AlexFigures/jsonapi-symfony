<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Fixtures\CustomRoute;

use JsonApi\Symfony\CustomRoute\Context\CustomRouteContext;
use JsonApi\Symfony\CustomRoute\Handler\CustomRouteHandlerInterface;
use JsonApi\Symfony\CustomRoute\Result\CustomRouteResult;

/**
 * Test handler for publishing articles.
 */
final class PublishArticleHandler implements CustomRouteHandlerInterface
{
    public function handle(CustomRouteContext $context): CustomRouteResult
    {
        $article = $context->getResource();

        // Business logic: check if already published
        if ($article->published) {
            return CustomRouteResult::conflict('Article is already published');
        }

        // Publish the article
        $article->published = true;
        $article->publishedAt = new \DateTimeImmutable();

        return CustomRouteResult::resource($article)
            ->withMeta([
                'publishedAt' => $article->publishedAt->format('c'),
                'message' => 'Article published successfully',
            ]);
    }
}
