<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Fixtures\Model;

use DateTimeImmutable;
use JsonApi\Symfony\Resource\Attribute\Attribute;
use JsonApi\Symfony\Resource\Attribute\Id;
use JsonApi\Symfony\Resource\Attribute\JsonApiResource;
use JsonApi\Symfony\Resource\Attribute\Relationship;
use JsonApi\Symfony\Resource\Attribute\SortableFields;

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

    #[Attribute(name: 'createdAt', writable: false)]
    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    #[Relationship(targetType: 'authors')]
    public function getAuthor(): Author
    {
        return $this->author;
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
