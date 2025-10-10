<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Integration\Fixtures\CustomRoute;

use Doctrine\ORM\EntityManagerInterface;
use JsonApi\Symfony\CustomRoute\Context\CustomRouteContext;
use JsonApi\Symfony\CustomRoute\Handler\CustomRouteHandlerInterface;
use JsonApi\Symfony\CustomRoute\Result\CustomRouteResult;
use JsonApi\Symfony\Tests\Integration\Fixtures\Entity\Article;

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

