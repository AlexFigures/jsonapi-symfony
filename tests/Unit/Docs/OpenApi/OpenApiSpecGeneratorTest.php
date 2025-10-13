<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Docs\OpenApi;

use AlexFigures\Symfony\Docs\OpenApi\OpenApiSpecGenerator;
use AlexFigures\Symfony\Http\Negotiation\MediaType;
use AlexFigures\Symfony\Resource\Metadata\AttributeMetadata;
use AlexFigures\Symfony\Resource\Metadata\RelationshipMetadata;
use AlexFigures\Symfony\Resource\Metadata\ResourceMetadata;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
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
            null, // No custom routes
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

    /**
     * Test that OpenAPI spec correctly handles resources with kebab-case type names.
     *
     * Note: Resource type format (kebab-case, snake_case, etc.) is defined in the
     * entity metadata, NOT by the jsonapi.routing.naming_convention config.
     * The naming_convention config only affects Symfony route names.
     */
    public function testHandlesKebabCaseResourceTypes(): void
    {
        $blogPostMetadata = new ResourceMetadata(
            'blog-posts',
            BlogPost::class,
            [
                'title' => new AttributeMetadata('title', types: ['string'], nullable: false),
            ],
            [],
            exposeId: true,
            idPropertyPath: 'id',
            routePrefix: '/api',
        );

        $registry = new class ($blogPostMetadata) implements ResourceRegistryInterface {
            public function __construct(private ResourceMetadata $blogPost)
            {
            }

            public function getByType(string $type): ResourceMetadata
            {
                return match ($type) {
                    'blog-posts' => $this->blogPost,
                    default => throw new \LogicException('Unknown type: ' . $type),
                };
            }

            public function hasType(string $type): bool
            {
                return $type === 'blog-posts';
            }

            public function getByClass(string $class): ?ResourceMetadata
            {
                return $class === BlogPost::class ? $this->blogPost : null;
            }

            public function all(): array
            {
                return [$this->blogPost];
            }
        };

        $generator = new OpenApiSpecGenerator(
            $registry,
            null, // No custom routes
            [
                'enabled' => true,
                'route' => '/_jsonapi/openapi.json',
                'title' => 'Test API',
                'version' => '1.0.0',
                'servers' => ['https://api.test'],
            ],
            '/api',
            'linkage',
        );

        $spec = $generator->generate();

        // Verify paths use kebab-case in URLs
        self::assertArrayHasKey('/api/blog-posts', $spec['paths']);
        self::assertArrayHasKey('/api/blog-posts/{id}', $spec['paths']);

        // Verify schema names use StudlyCase (BlogPosts not Blog-Posts)
        self::assertArrayHasKey('BlogPostsResource', $spec['components']['schemas']);
        self::assertArrayHasKey('BlogPostsResourceDocument', $spec['components']['schemas']);
        self::assertArrayHasKey('BlogPostsCollectionDocument', $spec['components']['schemas']);

        // Verify operation IDs use StudlyCase
        self::assertSame('listBlogPosts', $spec['paths']['/api/blog-posts']['get']['operationId']);
        self::assertSame('createBlogPosts', $spec['paths']['/api/blog-posts']['post']['operationId']);
        self::assertSame('getBlogPosts', $spec['paths']['/api/blog-posts/{id}']['get']['operationId']);
    }

    /**
     * Test that OpenAPI spec correctly handles resources with snake_case type names.
     *
     * Note: Resource type format (kebab-case, snake_case, etc.) is defined in the
     * entity metadata, NOT by the jsonapi.routing.naming_convention config.
     * The naming_convention config only affects Symfony route names.
     */
    public function testHandlesSnakeCaseResourceTypes(): void
    {
        $userProfileMetadata = new ResourceMetadata(
            'user_profiles',
            UserProfile::class,
            [
                'displayName' => new AttributeMetadata('displayName', types: ['string'], nullable: false),
            ],
            [],
            exposeId: true,
            idPropertyPath: 'id',
            routePrefix: '/api',
        );

        $registry = new class ($userProfileMetadata) implements ResourceRegistryInterface {
            public function __construct(private ResourceMetadata $userProfile)
            {
            }

            public function getByType(string $type): ResourceMetadata
            {
                return match ($type) {
                    'user_profiles' => $this->userProfile,
                    default => throw new \LogicException('Unknown type: ' . $type),
                };
            }

            public function hasType(string $type): bool
            {
                return $type === 'user_profiles';
            }

            public function getByClass(string $class): ?ResourceMetadata
            {
                return $class === UserProfile::class ? $this->userProfile : null;
            }

            public function all(): array
            {
                return [$this->userProfile];
            }
        };

        $generator = new OpenApiSpecGenerator(
            $registry,
            null, // No custom routes
            [
                'enabled' => true,
                'route' => '/_jsonapi/openapi.json',
                'title' => 'Test API',
                'version' => '1.0.0',
                'servers' => ['https://api.test'],
            ],
            '/api',
            'linkage',
        );

        $spec = $generator->generate();

        // Verify paths use snake_case in URLs
        self::assertArrayHasKey('/api/user_profiles', $spec['paths']);
        self::assertArrayHasKey('/api/user_profiles/{id}', $spec['paths']);

        // Verify schema names use StudlyCase (UserProfiles not User_Profiles)
        self::assertArrayHasKey('UserProfilesResource', $spec['components']['schemas']);
        self::assertArrayHasKey('UserProfilesResourceDocument', $spec['components']['schemas']);
        self::assertArrayHasKey('UserProfilesCollectionDocument', $spec['components']['schemas']);

        // Verify operation IDs use StudlyCase
        self::assertSame('listUserProfiles', $spec['paths']['/api/user_profiles']['get']['operationId']);
        self::assertSame('createUserProfiles', $spec['paths']['/api/user_profiles']['post']['operationId']);
        self::assertSame('getUserProfiles', $spec['paths']['/api/user_profiles/{id}']['get']['operationId']);
    }

    public function testRelationshipPathsPreserveResourceTypeFormat(): void
    {
        $blogPostMetadata = new ResourceMetadata(
            'blog-posts',
            BlogPost::class,
            [
                'title' => new AttributeMetadata('title', types: ['string'], nullable: false),
            ],
            [
                'post-author' => new RelationshipMetadata('post-author', false, 'authors', nullable: true),
            ],
            exposeId: true,
            idPropertyPath: 'id',
            routePrefix: '/api',
        );

        $authorMetadata = new ResourceMetadata(
            'authors',
            Author::class,
            [
                'name' => new AttributeMetadata('name', types: ['string'], nullable: false),
            ],
            [],
        );

        $registry = new class ($blogPostMetadata, $authorMetadata) implements ResourceRegistryInterface {
            public function __construct(
                private ResourceMetadata $blogPost,
                private ResourceMetadata $author,
            ) {
            }

            public function getByType(string $type): ResourceMetadata
            {
                return match ($type) {
                    'blog-posts' => $this->blogPost,
                    'authors' => $this->author,
                    default => throw new \LogicException('Unknown type: ' . $type),
                };
            }

            public function hasType(string $type): bool
            {
                return in_array($type, ['blog-posts', 'authors'], true);
            }

            public function getByClass(string $class): ?ResourceMetadata
            {
                return match ($class) {
                    BlogPost::class => $this->blogPost,
                    Author::class => $this->author,
                    default => null,
                };
            }

            public function all(): array
            {
                return [$this->blogPost, $this->author];
            }
        };

        $generator = new OpenApiSpecGenerator(
            $registry,
            null, // No custom routes
            [
                'enabled' => true,
                'route' => '/_jsonapi/openapi.json',
                'title' => 'Test API',
                'version' => '1.0.0',
                'servers' => ['https://api.test'],
            ],
            '/api',
            'linkage',
        );

        $spec = $generator->generate();

        // Verify relationship paths preserve kebab-case in resource type and relationship name
        self::assertArrayHasKey('/api/blog-posts/{id}/relationships/post-author', $spec['paths']);
        self::assertArrayHasKey('/api/blog-posts/{id}/post-author', $spec['paths']);

        // Verify operation IDs convert to StudlyCase
        $relPath = $spec['paths']['/api/blog-posts/{id}/relationships/post-author'];
        self::assertSame('getBlogPostsPostAuthorRelationship', $relPath['get']['operationId']);
        self::assertSame('updateBlogPostsPostAuthorRelationship', $relPath['patch']['operationId']);
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

final class BlogPost
{
    public function __construct(
        public string $id,
        public string $title,
    ) {
    }
}

final class UserProfile
{
    public function __construct(
        public string $id,
        public string $displayName,
    ) {
    }
}
