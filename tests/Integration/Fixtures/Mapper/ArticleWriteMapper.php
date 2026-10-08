<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Integration\Fixtures\Mapper;

use AlexFigures\JsonApi\Resource\Definition\ResourceDefinition;
use AlexFigures\JsonApi\Resource\Mapper\WriteMapperInterface;
use AlexFigures\JsonApi\Resource\Write\WriteContext;
use AlexFigures\JsonApi\Tests\Integration\Fixtures\Dto\ArticleCreateDto;
use AlexFigures\JsonApi\Tests\Integration\Fixtures\Dto\ArticleUpdateDto;
use AlexFigures\JsonApi\Tests\Integration\Fixtures\Entity\Article;

/**
 * Maps request DTOs to Article Entity.
 */
final class ArticleWriteMapper implements WriteMapperInterface
{
    public function instantiate(ResourceDefinition $definition, object $requestDto, WriteContext $context): object
    {
        if (!$requestDto instanceof ArticleCreateDto) {
            throw new \RuntimeException(sprintf('Expected ArticleCreateDto, got %s', get_debug_type($requestDto)));
        }

        $article = new Article();
        $article->setTitle($requestDto->title);
        $article->setContent($requestDto->content);

        return $article;
    }

    public function apply(object $entity, object $requestDto, ResourceDefinition $definition, WriteContext $context): void
    {
        if (!$entity instanceof Article) {
            throw new \RuntimeException(sprintf('Expected Article entity, got %s', get_debug_type($entity)));
        }

        if (!$requestDto instanceof ArticleUpdateDto) {
            throw new \RuntimeException(sprintf('Expected ArticleUpdateDto, got %s', get_debug_type($requestDto)));
        }

        // Apply only non-null fields (partial update support)
        if ($requestDto->title !== null) {
            $entity->setTitle($requestDto->title);
        }

        if ($requestDto->content !== null) {
            $entity->setContent($requestDto->content);
        }
    }
}
