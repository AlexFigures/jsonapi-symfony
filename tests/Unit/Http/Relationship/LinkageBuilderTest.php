<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Unit\Http\Relationship;

use BadMethodCallException;
use JsonApi\Symfony\Contract\Data\RelationshipReader;
use JsonApi\Symfony\Contract\Data\Slice;
use JsonApi\Symfony\Contract\Data\SliceIds;
use JsonApi\Symfony\Http\Relationship\LinkageBuilder;
use JsonApi\Symfony\Http\Request\PaginationConfig;
use JsonApi\Symfony\Query\Criteria;
use JsonApi\Symfony\Resource\Metadata\RelationshipMetadata;
use JsonApi\Symfony\Resource\Metadata\ResourceMetadata;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class LinkageBuilderTest extends TestCase
{
    public function testResolvesTargetTypeFromTargetClassMetadata(): void
    {
        $articleMetadata = new ResourceMetadata(
            'articles',
            ArticleResource::class,
            [],
            [
                'author' => new RelationshipMetadata('author', false, null, null, AuthorResource::class, false),
            ],
        );

        $authorMetadata = new ResourceMetadata('people', AuthorResource::class, [], []);

        $registry = new class($articleMetadata, $authorMetadata) implements ResourceRegistryInterface {
            public function __construct(
                private ResourceMetadata $article,
                private ResourceMetadata $author,
            ) {
            }

            public function getByType(string $type): ResourceMetadata
            {
                return match ($type) {
                    'articles' => $this->article,
                    'people' => $this->author,
                    default => throw new \LogicException(sprintf('Unknown resource type "%s".', $type)),
                };
            }

            public function hasType(string $type): bool
            {
                return in_array($type, ['articles', 'people'], true);
            }

            public function getByClass(string $class): ?ResourceMetadata
            {
                return match ($class) {
                    ArticleResource::class => $this->article,
                    AuthorResource::class => $this->author,
                    default => null,
                };
            }

            public function all(): array
            {
                return [$this->article, $this->author];
            }
        };

        $reader = new class implements RelationshipReader {
            public function getToOneId(string $type, string $id, string $rel): ?string
            {
                return $rel === 'author' ? '1' : null;
            }

            public function getToManyIds(string $type, string $id, string $rel, \JsonApi\Symfony\Query\Pagination $pagination): SliceIds
            {
                throw new BadMethodCallException('Not implemented.');
            }

            public function getRelatedResource(string $type, string $id, string $rel): ?object
            {
                return null;
            }

            public function getRelatedCollection(string $type, string $id, string $rel, Criteria $criteria): Slice
            {
                throw new BadMethodCallException('Not implemented.');
            }
        };

        $builder = new LinkageBuilder($registry, $reader, new PaginationConfig());

        [$kind, $data] = $builder->read('articles', '1', 'author', new Request());

        self::assertSame('to-one', $kind);
        self::assertSame(['type' => 'people', 'id' => '1'], $data);
    }

    public function testFallsBackToRelationshipNameWhenTypeIsRegistered(): void
    {
        $articleMetadata = new ResourceMetadata(
            'articles',
            ArticleResource::class,
            [],
            [
                'comments' => new RelationshipMetadata('comments'),
            ],
        );

        $commentMetadata = new ResourceMetadata('comments', CommentResource::class, [], []);

        $registry = new class($articleMetadata, $commentMetadata) implements ResourceRegistryInterface {
            public function __construct(
                private ResourceMetadata $article,
                private ResourceMetadata $comment,
            ) {
            }

            public function getByType(string $type): ResourceMetadata
            {
                return match ($type) {
                    'articles' => $this->article,
                    'comments' => $this->comment,
                    default => throw new \LogicException(sprintf('Unknown resource type "%s".', $type)),
                };
            }

            public function hasType(string $type): bool
            {
                return in_array($type, ['articles', 'comments'], true);
            }

            public function getByClass(string $class): ?ResourceMetadata
            {
                return match ($class) {
                    ArticleResource::class => $this->article,
                    CommentResource::class => $this->comment,
                    default => null,
                };
            }

            public function all(): array
            {
                return [$this->article, $this->comment];
            }
        };

        $reader = new class implements RelationshipReader {
            public function getToOneId(string $type, string $id, string $rel): ?string
            {
                return $rel === 'comments' ? '2' : null;
            }

            public function getToManyIds(string $type, string $id, string $rel, \JsonApi\Symfony\Query\Pagination $pagination): SliceIds
            {
                throw new BadMethodCallException('Not implemented.');
            }

            public function getRelatedResource(string $type, string $id, string $rel): ?object
            {
                return null;
            }

            public function getRelatedCollection(string $type, string $id, string $rel, Criteria $criteria): Slice
            {
                throw new BadMethodCallException('Not implemented.');
            }
        };

        $builder = new LinkageBuilder($registry, $reader, new PaginationConfig());

        [$kind, $data] = $builder->read('articles', '1', 'comments', new Request());

        self::assertSame('to-one', $kind);
        self::assertSame(['type' => 'comments', 'id' => '2'], $data);
    }
}

final class ArticleResource
{
}

final class AuthorResource
{
}

final class CommentResource
{
}
