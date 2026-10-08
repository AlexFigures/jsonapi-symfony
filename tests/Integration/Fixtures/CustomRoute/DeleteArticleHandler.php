<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Integration\Fixtures\CustomRoute;

use AlexFigures\JsonApi\CustomRoute\Context\CustomRouteContext;
use AlexFigures\JsonApi\CustomRoute\Handler\CustomRouteHandlerInterface;
use AlexFigures\JsonApi\CustomRoute\Result\CustomRouteResult;
use AlexFigures\JsonApi\Tests\Integration\Fixtures\Entity\Article;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Test handler for deleting an article (custom delete endpoint).
 */
final readonly class DeleteArticleHandler implements CustomRouteHandlerInterface
{
    public function __construct(
        private EntityManagerInterface $em
    ) {
    }

    public function handle(CustomRouteContext $context): CustomRouteResult
    {
        /** @var Article $article */
        $article = $context->getResource();

        $this->em->remove($article);
        $this->em->flush();

        return CustomRouteResult::noContent();
    }
}
