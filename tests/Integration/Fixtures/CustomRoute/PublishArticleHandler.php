<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Integration\Fixtures\CustomRoute;

use AlexFigures\JsonApi\CustomRoute\Context\CustomRouteContext;
use AlexFigures\JsonApi\CustomRoute\Handler\CustomRouteHandlerInterface;
use AlexFigures\JsonApi\CustomRoute\Result\CustomRouteResult;
use AlexFigures\JsonApi\Tests\Integration\Fixtures\Entity\Article;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Test handler for publishing an article.
 *
 * This is a write operation that modifies the article, so it runs in a transaction.
 */
final readonly class PublishArticleHandler implements CustomRouteHandlerInterface
{
    public function __construct(
        private EntityManagerInterface $em
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
