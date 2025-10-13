<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Fixtures\CustomRoute;

use AlexFigures\Symfony\CustomRoute\Context\CustomRouteContext;
use AlexFigures\Symfony\CustomRoute\Handler\CustomRouteHandlerInterface;
use AlexFigures\Symfony\CustomRoute\Result\CustomRouteResult;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Article;
use Doctrine\ORM\EntityManagerInterface;

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
