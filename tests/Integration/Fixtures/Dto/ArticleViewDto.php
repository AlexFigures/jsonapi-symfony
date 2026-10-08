<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Integration\Fixtures\Dto;

use AlexFigures\JsonApi\Resource\Attribute\Attribute;
use AlexFigures\JsonApi\Resource\Attribute\Id;
use AlexFigures\JsonApi\Resource\Attribute\JsonApiResource;
use AlexFigures\JsonApi\Resource\Attribute\Relationship;
use AlexFigures\JsonApi\Resource\Definition\ReadProjection;
use DateTimeImmutable;

/**
 * View DTO for Article resource.
 *
 * This DTO is used for reading Article data without exposing
 * the full Entity with all its Doctrine metadata.
 */
#[JsonApiResource(
    type: 'article-dtos',
    dataClass: \AlexFigures\JsonApi\Tests\Integration\Fixtures\Entity\Article::class,
    viewClass: self::class,
    readProjection: ReadProjection::DTO,
)]
final readonly class ArticleViewDto
{
    public function __construct(
        #[Id]
        #[Attribute]
        public string $id,
        #[Attribute]
        public string $title,
        #[Attribute]
        public string $content,
        #[Attribute(name: 'createdAt')]
        public ?DateTimeImmutable $createdAt = null,
    ) {
    }
}
