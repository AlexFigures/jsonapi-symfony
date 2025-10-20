<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Fixtures\Mapper;

use AlexFigures\Symfony\Query\Criteria;
use AlexFigures\Symfony\Resource\Definition\ResourceDefinition;
use AlexFigures\Symfony\Resource\Mapper\ReadMapperInterface;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Dto\ArticleViewDto;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Article;

/**
 * Maps Article Entity to ArticleViewDto.
 */
final class ArticleReadMapper implements ReadMapperInterface
{
    public function toView(mixed $row, ResourceDefinition $definition, Criteria $criteria): object
    {
        // If already a DTO, return as-is
        if ($row instanceof ArticleViewDto) {
            return $row;
        }

        // If it's an Entity, map to DTO
        if ($row instanceof Article) {
            return new ArticleViewDto(
                id: $row->getId(),
                title: $row->getTitle(),
                content: $row->getContent(),
                createdAt: $row->getCreatedAt(),
            );
        }

        // If it's an array (from DQL projection), construct DTO
        if (is_array($row)) {
            return new ArticleViewDto(
                id: $row['id'] ?? throw new \RuntimeException('Missing id in projection'),
                title: $row['title'] ?? throw new \RuntimeException('Missing title in projection'),
                content: $row['content'] ?? throw new \RuntimeException('Missing content in projection'),
                createdAt: $row['createdAt'] ?? null,
            );
        }

        throw new \RuntimeException(sprintf('Cannot map %s to ArticleViewDto', get_debug_type($row)));
    }
}
