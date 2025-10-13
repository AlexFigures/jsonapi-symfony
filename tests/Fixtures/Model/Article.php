<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Fixtures\Model;

use AlexFigures\Symfony\Resource\Attribute\Attribute;
use AlexFigures\Symfony\Resource\Attribute\Id;
use AlexFigures\Symfony\Resource\Attribute\JsonApiResource;
use AlexFigures\Symfony\Resource\Attribute\Relationship;
use AlexFigures\Symfony\Resource\Attribute\SortableFields;
use DateTimeImmutable;

#[JsonApiResource(type: 'articles')]
#[SortableFields(['title', 'createdAt'])]
final class Article
{
    /** @var list<Tag> */
    private array $tags = [];

    public function __construct(
        #[Id]
        #[Attribute]
        public string $id,
        #[Attribute]
        public string $title,
        private DateTimeImmutable $createdAt,
        private Author $author,
        Tag ...$tags,
    ) {
        $this->tags = $tags === [] ? [] : array_values($tags);
    }

    #[Attribute(name: 'createdAt')]
    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    #[Relationship(targetType: 'authors')]
    public function getAuthor(): Author
    {
        return $this->author;
    }

    public function setAuthor(Author $author): void
    {
        $this->author = $author;
    }

    /**
     * @return list<Tag>
     */
    #[Relationship(toMany: true, targetType: 'tags')]
    public function getTags(): array
    {
        return $this->tags;
    }
}
