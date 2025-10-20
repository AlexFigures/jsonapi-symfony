<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Fixtures\Dto;

use AlexFigures\Symfony\Resource\Attribute\Attribute;
use AlexFigures\Symfony\Resource\Attribute\Id;
use AlexFigures\Symfony\Resource\Attribute\JsonApiResource;
use AlexFigures\Symfony\Resource\Attribute\Relationship;
use AlexFigures\Symfony\Resource\Definition\ReadProjection;
use DateTimeImmutable;

/**
 * View DTO for Article resource.
 *
 * This DTO is used for reading Article data without exposing
 * the full Entity with all its Doctrine metadata.
 */
#[JsonApiResource(
    type: 'article-dtos',
    dataClass: \AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Article::class,
    viewClass: self::class,
    readProjection: ReadProjection::DTO,
)]
final class ArticleViewDto
{
    public function __construct(
        #[Id]
        #[Attribute]
        public readonly string $id,
        #[Attribute]
        public readonly string $title,
        #[Attribute]
        public readonly string $content,
        #[Attribute(name: 'createdAt')]
        public readonly ?DateTimeImmutable $createdAt = null,
    ) {
    }
}
