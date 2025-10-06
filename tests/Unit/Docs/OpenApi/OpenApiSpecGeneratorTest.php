<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Unit\Docs\OpenApi;

use JsonApi\Symfony\Docs\OpenApi\OpenApiSpecGenerator;
use JsonApi\Symfony\Http\Negotiation\MediaType;
use JsonApi\Symfony\Resource\Metadata\AttributeMetadata;
use JsonApi\Symfony\Resource\Metadata\RelationshipMetadata;
use JsonApi\Symfony\Resource\Metadata\ResourceMetadata;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;
use PHPUnit\Framework\TestCase;

final class OpenApiSpecGeneratorTest extends TestCase
{
    public function testGeneratesSpecificationForResources(): void
    {
        $articleMetadata = new ResourceMetadata(
            'articles',
            Article::class,
            [
                'title' => new AttributeMetadata('title', types: ['string'], nullable: false),
                'publishedAt' => new AttributeMetadata('publishedAt', types: [\DateTimeImmutable::class], nullable: false),
            ],
            [
                'author' => new RelationshipMetadata('author', false, 'authors', nullable: false),
                'tags' => new RelationshipMetadata('tags', true, 'tags'),
            ],
            exposeId: true,
            idPropertyPath: 'id',
            routePrefix: '/api',
            description: 'Article resources',
        );

        $authorMetadata = new ResourceMetadata(
            'authors',
            Author::class,
            [
                'name' => new AttributeMetadata('name', types: ['string'], nullable: false),
            ],
            [],
        );

        $tagMetadata = new ResourceMetadata(
            'tags',
            Tag::class,
            [
                'name' => new AttributeMetadata('name', types: ['string'], nullable: false),
            ],
            [],
        );

        $registry = new class ($articleMetadata, $authorMetadata, $tagMetadata) implements ResourceRegistryInterface {
            /** @var list<ResourceMetadata> */
            private array $all;

            public function __construct(
                private ResourceMetadata $article,
                private ResourceMetadata $author,
                private ResourceMetadata $tag,
            ) {
                $this->all = [$article, $author, $tag];
            }

            public function getByType(string $type): ResourceMetadata
            {
                return match ($type) {
                    'articles' => $this->article,
                    'authors' => $this->author,
                    'tags' => $this->tag,
                    default => throw new \LogicException('Unknown type: ' . $type),
                };
            }

            public function hasType(string $type): bool
            {
                return in_array($type, ['articles', 'authors', 'tags'], true);
            }

            public function getByClass(string $class): ?ResourceMetadata
            {
                return match ($class) {
                    Article::class => $this->article,
                    Author::class => $this->author,
                    Tag::class => $this->tag,
                    default => null,
                };
            }

            public function all(): array
            {
                return $this->all;
            }
        };

        $generator = new OpenApiSpecGenerator(
            $registry,
            [
                'enabled' => true,
                'route' => '/_jsonapi/openapi.json',
                'title' => 'Test API',
                'version' => '1.2.3',
                'servers' => ['https://api.test'],
            ],
            '/api',
            'linkage',
        );

        $spec = $generator->generate();

        self::assertSame('3.1.0', $spec['openapi']);
        self::assertSame('Test API', $spec['info']['title']);
        self::assertSame('1.2.3', $spec['info']['version']);
        self::assertSame([['url' => 'https://api.test']], $spec['servers']);
        self::assertArrayHasKey('/api/articles', $spec['paths']);
        self::assertArrayHasKey(MediaType::JSON_API, $spec['paths']['/api/articles']['get']['responses']['200']['content']);
        self::assertSame(
            '#/components/schemas/ArticlesCollectionDocument',
            $spec['paths']['/api/articles']['get']['responses']['200']['content'][MediaType::JSON_API]['schema']['$ref'],
        );
        self::assertSame(
            '#/components/schemas/ArticlesResourceDocument',
            $spec['paths']['/api/articles/{id}']['get']['responses']['200']['content'][MediaType::JSON_API]['schema']['$ref'],
        );
        self::assertSame(
            'string',
            $spec['components']['schemas']['ArticlesResource']['properties']['attributes']['properties']['title']['type'],
        );
        self::assertSame(
            'date-time',
            $spec['components']['schemas']['ArticlesResource']['properties']['attributes']['properties']['publishedAt']['format'],
        );
        self::assertSame(
            '#/components/schemas/AuthorsIdentifier',
            $spec['components']['schemas']['ArticlesAuthorRelationshipDocument']['properties']['data']['$ref'] ?? null,
        );
        self::assertSame(
            '#/components/schemas/TagsCollectionDocument',
            $spec['paths']['/api/articles/{id}/tags']['get']['responses']['200']['content'][MediaType::JSON_API]['schema']['$ref'],
        );
    }
}

final class Article
{
    public function __construct(
        public string $id,
        public string $title,
        public \DateTimeImmutable $publishedAt,
    ) {
    }
}

final class Author
{
    public function __construct(
        public string $id,
        public string $name,
    ) {
    }
}

final class Tag
{
    public function __construct(
        public string $id,
        public string $name,
    ) {
    }
}
