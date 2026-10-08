<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Integration\Fixtures\CustomRoute;

use AlexFigures\JsonApi\CustomRoute\Context\CustomRouteContext;
use AlexFigures\JsonApi\CustomRoute\Handler\CustomRouteHandlerInterface;
use AlexFigures\JsonApi\CustomRoute\Result\CustomRouteResult;
use AlexFigures\JsonApi\Tests\Integration\Fixtures\Entity\Article;
use AlexFigures\JsonApi\Tests\Integration\Fixtures\Entity\Tag;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Test handler for creating an article with tags in one operation.
 *
 * This demonstrates a custom creation endpoint with business logic.
 */
final readonly class CreateArticleWithTagsHandler implements CustomRouteHandlerInterface
{
    public function __construct(
        private EntityManagerInterface $em
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
