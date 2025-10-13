<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Fixtures\CustomRoute;

use AlexFigures\Symfony\CustomRoute\Context\CustomRouteContext;
use AlexFigures\Symfony\CustomRoute\Handler\CustomRouteHandlerInterface;
use AlexFigures\Symfony\CustomRoute\Result\CustomRouteResult;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Article;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Tag;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Test handler for creating an article with tags in one operation.
 *
 * This demonstrates a custom creation endpoint with business logic.
 */
final class CreateArticleWithTagsHandler implements CustomRouteHandlerInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em
    ) {
    }

    public function handle(CustomRouteContext $context): CustomRouteResult
    {
        $body = $context->getBody();

        // Validate required fields
        if (!isset($body['title']) || !isset($body['content'])) {
            return CustomRouteResult::badRequest('Missing required fields: title, content');
        }

        // Create article
        $article = new Article();
        $article->setTitle($body['title']);
        $article->setContent($body['content']);

        // Add tags if provided
        if (isset($body['tagIds']) && is_array($body['tagIds'])) {
            foreach ($body['tagIds'] as $tagId) {
                $tag = $this->em->find(Tag::class, $tagId);
                if ($tag !== null) {
                    $article->addTag($tag);
                }
            }
        }

        $this->em->persist($article);
        $this->em->flush();

        return CustomRouteResult::created($article);
    }
}
