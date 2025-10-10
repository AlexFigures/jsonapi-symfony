<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Functional\Docs;

use JsonApi\Symfony\Docs\OpenApi\OpenApiSpecGenerator;
use JsonApi\Symfony\Http\Controller\OpenApiController;
use JsonApi\Symfony\Http\Negotiation\MediaType;
use JsonApi\Symfony\Resource\Metadata\AttributeMetadata;
use JsonApi\Symfony\Resource\Metadata\CustomRouteMetadata;
use JsonApi\Symfony\Resource\Metadata\RelationshipMetadata;
use JsonApi\Symfony\Resource\Metadata\ResourceMetadata;
use JsonApi\Symfony\Resource\Registry\CustomRouteRegistry;
use JsonApi\Symfony\Resource\Registry\CustomRouteRegistryInterface;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;
use JsonApi\Symfony\Tests\Fixtures\CustomRoute\PublishArticleHandler;
use JsonApi\Symfony\Tests\Fixtures\Model\Article;
use JsonApi\Symfony\Tests\Fixtures\Model\Author;
use JsonApi\Symfony\Tests\Fixtures\Model\Tag;
use PHPUnit\Framework\TestCase;

/**
 * Functional tests for OpenApiController.
 *
 * These tests verify that the OpenAPI specification is correctly generated
 * with real resource metadata and different naming conventions.
 *
 * Test Coverage:
 * - Full OpenAPI spec generation with real resources
 * - Naming convention: snake_case (default)
 * - Naming convention: kebab-case
 * - Resource paths and operation IDs
 * - Relationship paths
 * - Schema generation
 */
final class OpenApiControllerTest extends TestCase
{
    public function testGeneratesCompleteSpecificationWithSnakeCaseNaming(): void
    {
        $registry = $this->createRegistry();

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

        $controller = new OpenApiController(
            $generator,
            ['enabled' => true],
        );

        $response = ($controller)();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/vnd.oai.openapi+json', $response->headers->get('Content-Type'));

        $spec = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        // Verify OpenAPI version and info
        self::assertSame('3.1.0', $spec['openapi']);
        self::assertSame('Test API', $spec['info']['title']);
        self::assertSame('1.0.0', $spec['info']['version']);

        // Verify servers
        self::assertCount(1, $spec['servers']);
        self::assertSame('https://api.test', $spec['servers'][0]['url']);

        // Verify tags
        self::assertCount(3, $spec['tags']);
        $tagNames = array_column($spec['tags'], 'name');
        self::assertContains('articles', $tagNames);
        self::assertContains('authors', $tagNames);
        self::assertContains('tags', $tagNames);

        // Verify paths exist for all resources
        self::assertArrayHasKey('/api/articles', $spec['paths']);
        self::assertArrayHasKey('/api/articles/{id}', $spec['paths']);
        self::assertArrayHasKey('/api/authors', $spec['paths']);
        self::assertArrayHasKey('/api/authors/{id}', $spec['paths']);
        self::assertArrayHasKey('/api/tags', $spec['paths']);
        self::assertArrayHasKey('/api/tags/{id}', $spec['paths']);

        // Verify collection operations
        self::assertArrayHasKey('get', $spec['paths']['/api/articles']);
        self::assertArrayHasKey('post', $spec['paths']['/api/articles']);

        // Verify resource operations
        self::assertArrayHasKey('get', $spec['paths']['/api/articles/{id}']);
        self::assertArrayHasKey('patch', $spec['paths']['/api/articles/{id}']);
        self::assertArrayHasKey('delete', $spec['paths']['/api/articles/{id}']);

        // Verify operation IDs use StudlyCase
        self::assertSame('listArticles', $spec['paths']['/api/articles']['get']['operationId']);
        self::assertSame('createArticles', $spec['paths']['/api/articles']['post']['operationId']);
        self::assertSame('getArticles', $spec['paths']['/api/articles/{id}']['get']['operationId']);
        self::assertSame('updateArticles', $spec['paths']['/api/articles/{id}']['patch']['operationId']);
        self::assertSame('deleteArticles', $spec['paths']['/api/articles/{id}']['delete']['operationId']);

        // Verify schemas
        self::assertArrayHasKey('ArticlesResource', $spec['components']['schemas']);
        self::assertArrayHasKey('ArticlesResourceDocument', $spec['components']['schemas']);
        self::assertArrayHasKey('ArticlesCollectionDocument', $spec['components']['schemas']);
        self::assertArrayHasKey('AuthorsResource', $spec['components']['schemas']);
        self::assertArrayHasKey('TagsResource', $spec['components']['schemas']);

        // Verify resource schema structure
        $articleSchema = $spec['components']['schemas']['ArticlesResource'];
        self::assertSame('object', $articleSchema['type']);
        self::assertArrayHasKey('type', $articleSchema['properties']);
        self::assertArrayHasKey('id', $articleSchema['properties']);
        self::assertArrayHasKey('attributes', $articleSchema['properties']);
        self::assertArrayHasKey('relationships', $articleSchema['properties']);

        // Verify attributes in schema
        self::assertArrayHasKey('title', $articleSchema['properties']['attributes']['properties']);
        self::assertArrayHasKey('content', $articleSchema['properties']['attributes']['properties']);

        // Verify content type in responses
        $getResponse = $spec['paths']['/api/articles']['get']['responses']['200'];
        self::assertArrayHasKey(MediaType::JSON_API, $getResponse['content']);
    }

    public function testGeneratesRelationshipPaths(): void
    {
        $registry = $this->createRegistry();

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

        $controller = new OpenApiController(
            $generator,
            ['enabled' => true],
        );

        $response = ($controller)();
        $spec = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        // Verify relationship paths exist
        self::assertArrayHasKey('/api/articles/{id}/relationships/author', $spec['paths']);
        self::assertArrayHasKey('/api/articles/{id}/relationships/tags', $spec['paths']);

        // Verify related resource paths exist
        self::assertArrayHasKey('/api/articles/{id}/author', $spec['paths']);
        self::assertArrayHasKey('/api/articles/{id}/tags', $spec['paths']);

        // Verify relationship operations
        $authorRelPath = $spec['paths']['/api/articles/{id}/relationships/author'];
        self::assertArrayHasKey('get', $authorRelPath);
        self::assertArrayHasKey('patch', $authorRelPath);
        self::assertArrayNotHasKey('post', $authorRelPath); // to-one relationship
        self::assertArrayNotHasKey('delete', $authorRelPath); // to-one relationship

        $tagsRelPath = $spec['paths']['/api/articles/{id}/relationships/tags'];
        self::assertArrayHasKey('get', $tagsRelPath);
        self::assertArrayHasKey('patch', $tagsRelPath);
        self::assertArrayHasKey('post', $tagsRelPath); // to-many relationship
        self::assertArrayHasKey('delete', $tagsRelPath); // to-many relationship

        // Verify relationship operation IDs
        self::assertSame('getArticlesAuthorRelationship', $authorRelPath['get']['operationId']);
        self::assertSame('updateArticlesAuthorRelationship', $authorRelPath['patch']['operationId']);
        self::assertSame('getArticlesTagsRelationship', $tagsRelPath['get']['operationId']);
        self::assertSame('updateArticlesTagsRelationship', $tagsRelPath['patch']['operationId']);
        self::assertSame('addArticlesTagsRelationship', $tagsRelPath['post']['operationId']);
        self::assertSame('removeArticlesTagsRelationship', $tagsRelPath['delete']['operationId']);

        // Verify related resource operation IDs
        self::assertSame('getArticlesAuthorRelated', $spec['paths']['/api/articles/{id}/author']['get']['operationId']);
        self::assertSame('getArticlesTagsRelated', $spec['paths']['/api/articles/{id}/tags']['get']['operationId']);

        // Verify relationship schemas
        self::assertArrayHasKey('ArticlesAuthorRelationshipDocument', $spec['components']['schemas']);
        self::assertArrayHasKey('ArticlesTagsRelationshipDocument', $spec['components']['schemas']);
    }

    public function testGeneratesCorrectSchemaReferences(): void
    {
        $registry = $this->createRegistry();

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

        $controller = new OpenApiController(
            $generator,
            ['enabled' => true],
        );

        $response = ($controller)();
        $spec = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        // Verify collection GET response references collection document
        $collectionGetResponse = $spec['paths']['/api/articles']['get']['responses']['200']['content'][MediaType::JSON_API];
        self::assertSame('#/components/schemas/ArticlesCollectionDocument', $collectionGetResponse['schema']['$ref']);

        // Verify resource GET response references resource document
        $resourceGetResponse = $spec['paths']['/api/articles/{id}']['get']['responses']['200']['content'][MediaType::JSON_API];
        self::assertSame('#/components/schemas/ArticlesResourceDocument', $resourceGetResponse['schema']['$ref']);

        // Verify POST request body references resource document
        $postRequestBody = $spec['paths']['/api/articles']['post']['requestBody']['content'][MediaType::JSON_API];
        self::assertSame('#/components/schemas/ArticlesResourceDocument', $postRequestBody['schema']['$ref']);

        // Verify relationship references
        $authorRelSchema = $spec['components']['schemas']['ArticlesAuthorRelationshipDocument'];
        self::assertSame('#/components/schemas/AuthorsIdentifier', $authorRelSchema['properties']['data']['$ref']);

        $tagsRelSchema = $spec['components']['schemas']['ArticlesTagsRelationshipDocument'];
        self::assertSame('#/components/schemas/TagsIdentifier', $tagsRelSchema['properties']['data']['items']['$ref']);
    }

    public function testHandlesMultipleServers(): void
    {
        $registry = $this->createRegistry();

        $generator = new OpenApiSpecGenerator(
            $registry,
            null, // No custom routes
            [
                'enabled' => true,
                'route' => '/_jsonapi/openapi.json',
                'title' => 'Test API',
                'version' => '1.0.0',
                'servers' => [
                    'https://api.production.test',
                    'https://api.staging.test',
                    'http://localhost:8000',
                ],
            ],
            '/api',
            'linkage',
        );

        $controller = new OpenApiController(
            $generator,
            ['enabled' => true],
        );

        $response = ($controller)();
        $spec = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertCount(3, $spec['servers']);
        self::assertSame('https://api.production.test', $spec['servers'][0]['url']);
        self::assertSame('https://api.staging.test', $spec['servers'][1]['url']);
        self::assertSame('http://localhost:8000', $spec['servers'][2]['url']);
    }

    private function createRegistry(): ResourceRegistryInterface
    {
        $articleMetadata = new ResourceMetadata(
            'articles',
            Article::class,
            [
                'title' => new AttributeMetadata('title', types: ['string'], nullable: false),
                'content' => new AttributeMetadata('content', types: ['string'], nullable: false),
            ],
            [
                'author' => new RelationshipMetadata('author', false, 'authors', nullable: true),
                'tags' => new RelationshipMetadata('tags', true, 'tags'),
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
            exposeId: true,
            idPropertyPath: 'id',
            routePrefix: '/api',
        );

        $tagMetadata = new ResourceMetadata(
            'tags',
            Tag::class,
            [
                'name' => new AttributeMetadata('name', types: ['string'], nullable: false),
            ],
            [],
            exposeId: true,
            idPropertyPath: 'id',
            routePrefix: '/api',
        );

        return new class ($articleMetadata, $authorMetadata, $tagMetadata) implements ResourceRegistryInterface {
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
    }

    public function testIncludesCustomRoutesInSpecification(): void
    {
        $registry = $this->createRegistry();
        $customRouteRegistry = $this->createCustomRouteRegistry();

        $generator = new OpenApiSpecGenerator(
            $registry,
            $customRouteRegistry,
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

        $controller = new OpenApiController(
            $generator,
            ['enabled' => true],
        );

        $response = ($controller)();
        $spec = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        // Verify custom route paths are included
        self::assertArrayHasKey('/api/articles/{id}/publish', $spec['paths']);
        self::assertArrayHasKey('/api/articles/search', $spec['paths']);

        // Verify custom route operations
        $publishPath = $spec['paths']['/api/articles/{id}/publish'];
        self::assertArrayHasKey('post', $publishPath);
        self::assertSame('publishArticle', $publishPath['post']['operationId']);
        self::assertSame('Publish an article', $publishPath['post']['summary']);
        self::assertSame('articles', $publishPath['post']['tags'][0]);

        $searchPath = $spec['paths']['/api/articles/search'];
        self::assertArrayHasKey('get', $searchPath);
        self::assertSame('searchArticles', $searchPath['get']['operationId']);
        self::assertSame('Search articles', $searchPath['get']['summary']);
        self::assertSame('articles', $searchPath['get']['tags'][0]);

        // Verify custom routes have proper request/response schemas
        self::assertArrayHasKey('responses', $publishPath['post']);
        self::assertArrayHasKey('200', $publishPath['post']['responses']);
    }

    /**
     * Test that custom routes preserve the resource type format in URL paths.
     *
     * Note: The jsonapi.routing.naming_convention config affects ONLY route names
     * (internal Symfony identifiers), NOT the URL paths. URL paths always use
     * the resource type as defined in the entity metadata.
     *
     * For example, with resource type 'blog-posts':
     * - Route name (with kebab-case convention): jsonapi.blog-posts.index
     * - URL path: /api/blog-posts (uses resource type as-is)
     */
    public function testCustomRoutesPreserveResourceTypeFormat(): void
    {
        // Create registry with kebab-case resource type
        $blogPostMetadata = new ResourceMetadata(
            'blog-posts',
            Article::class,
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
                return $class === Article::class ? $this->blogPost : null;
            }

            public function all(): array
            {
                return [$this->blogPost];
            }
        };

        // Create custom route with kebab-case in path
        $customRouteRegistry = new CustomRouteRegistry();
        $customRouteRegistry->addRoute(new CustomRouteMetadata(
            name: 'blog-posts.publish',
            path: '/api/blog-posts/{id}/publish',
            methods: ['POST'],
            handler: PublishArticleHandler::class,
            controller: null,
            resourceType: 'blog-posts',
            defaults: [],
            requirements: [],
            description: 'Publish a blog post',
            priority: 0,
        ));

        $generator = new OpenApiSpecGenerator(
            $registry,
            $customRouteRegistry,
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

        $controller = new OpenApiController(
            $generator,
            ['enabled' => true],
        );

        $response = ($controller)();
        $spec = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        // Verify path preserves the resource type format (kebab-case in this case)
        self::assertArrayHasKey('/api/blog-posts/{id}/publish', $spec['paths']);

        // Verify operation ID uses StudlyCase and singular form (because path contains {id})
        $publishPath = $spec['paths']['/api/blog-posts/{id}/publish'];
        self::assertSame('publishBlogPost', $publishPath['post']['operationId']);

        // Verify tag uses original resource type format
        self::assertSame('blog-posts', $publishPath['post']['tags'][0]);
    }

    private function createCustomRouteRegistry(): CustomRouteRegistryInterface
    {
        $registry = new CustomRouteRegistry();

        // Add a custom route for publishing articles
        $registry->addRoute(new CustomRouteMetadata(
            name: 'articles.publish',
            path: '/api/articles/{id}/publish',
            methods: ['POST'],
            handler: PublishArticleHandler::class,
            controller: null,
            resourceType: 'articles',
            defaults: [],
            requirements: [],
            description: 'Publish an article',
            priority: 0,
        ));

        // Add a custom route for searching articles
        $registry->addRoute(new CustomRouteMetadata(
            name: 'articles.search',
            path: '/api/articles/search',
            methods: ['GET'],
            handler: PublishArticleHandler::class, // Reusing handler for test
            controller: null,
            resourceType: 'articles',
            defaults: [],
            requirements: [],
            description: 'Search articles',
            priority: 0,
        ));

        return $registry;
    }
}
