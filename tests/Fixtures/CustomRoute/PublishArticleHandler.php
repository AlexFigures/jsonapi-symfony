<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Fixtures\CustomRoute;

use AlexFigures\Symfony\CustomRoute\Context\CustomRouteContext;
use AlexFigures\Symfony\CustomRoute\Handler\CustomRouteHandlerInterface;
use AlexFigures\Symfony\CustomRoute\Result\CustomRouteResult;

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
