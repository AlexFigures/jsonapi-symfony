<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Integration\Fixtures\CustomRoute;

use Doctrine\ORM\EntityManagerInterface;
use JsonApi\Symfony\CustomRoute\Context\CustomRouteContext;
use JsonApi\Symfony\CustomRoute\Handler\CustomRouteHandlerInterface;
use JsonApi\Symfony\CustomRoute\Result\CustomRouteResult;
use JsonApi\Symfony\Tests\Integration\Fixtures\Entity\Article;

/**
 * Test handler for publishing an article.
 *
 * This is a write operation that modifies the article, so it runs in a transaction.
 */
final class PublishArticleHandler implements CustomRouteHandlerInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em
    ) {
    }

    public function handle(CustomRouteContext $context): CustomRouteResult
    {
        /** @var Article $article */
        $article = $context->getResource();

        // Simulate publishing logic
        $article->setTitle($article->getTitle() . ' [PUBLISHED]');

        $this->em->flush();

        return CustomRouteResult::resource($article);
    }
}
