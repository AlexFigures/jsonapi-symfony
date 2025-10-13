<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Fixtures\CustomRoute;

use AlexFigures\Symfony\CustomRoute\Context\CustomRouteContext;
use AlexFigures\Symfony\CustomRoute\Handler\CustomRouteHandlerInterface;
use AlexFigures\Symfony\CustomRoute\Result\CustomRouteResult;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Article;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Test handler for deleting an article (custom delete endpoint).
 */
final class DeleteArticleHandler implements CustomRouteHandlerInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em
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
