<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Http\Controller;

use AlexFigures\Symfony\Bridge\Doctrine\Repository\GenericDoctrineRepository;
use AlexFigures\Symfony\Bridge\Doctrine\Transaction\DoctrineTransactionManager;
use AlexFigures\Symfony\CustomRoute\Context\CustomRouteContextFactory;
use AlexFigures\Symfony\CustomRoute\Controller\CustomRouteController;
use AlexFigures\Symfony\CustomRoute\Handler\CustomRouteHandlerRegistry;
use AlexFigures\Symfony\CustomRoute\Response\CustomRouteResponseBuilder;
use AlexFigures\Symfony\Filter\Compiler\Doctrine\DoctrineFilterCompiler;
use AlexFigures\Symfony\Filter\Handler\Registry\FilterHandlerRegistry;
use AlexFigures\Symfony\Filter\Handler\Registry\SortHandlerRegistry;
use AlexFigures\Symfony\Filter\Operator\EqualOperator;
use AlexFigures\Symfony\Filter\Operator\Registry;
use AlexFigures\Symfony\Filter\Parser\FilterParser;
use AlexFigures\Symfony\Http\Document\DocumentBuilder;
use AlexFigures\Symfony\Http\Error\ErrorBuilder;
use AlexFigures\Symfony\Http\Error\ErrorMapper;
use AlexFigures\Symfony\Http\Link\LinkGenerator;
use AlexFigures\Symfony\Http\Negotiation\MediaType;
use AlexFigures\Symfony\Http\Request\FilteringWhitelist;
use AlexFigures\Symfony\Http\Request\PaginationConfig;
use AlexFigures\Symfony\Http\Request\QueryParser;
use AlexFigures\Symfony\Http\Request\SortingWhitelist;
use AlexFigures\Symfony\Resource\Mapper\DefaultReadMapper;
use AlexFigures\Symfony\Resource\Metadata\CustomRouteMetadata;
use AlexFigures\Symfony\Resource\Registry\CustomRouteRegistry;
use AlexFigures\Symfony\Tests\Integration\DoctrineIntegrationTestCase;
use AlexFigures\Symfony\Tests\Integration\Fixtures\CustomRoute\CategorySynonymsByCategoryHandler;
use AlexFigures\Symfony\Tests\Integration\Fixtures\CustomRoute\CreateArticleWithTagsHandler;
use AlexFigures\Symfony\Tests\Integration\Fixtures\CustomRoute\DeleteArticleHandler;
use AlexFigures\Symfony\Tests\Integration\Fixtures\CustomRoute\ErrorReturningHandler;
use AlexFigures\Symfony\Tests\Integration\Fixtures\CustomRoute\FailingHandler;
use AlexFigures\Symfony\Tests\Integration\Fixtures\CustomRoute\PublishArticleHandler;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Article;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Category;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\CategorySynonym;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Tag;
use AlexFigures\Symfony\Tests\Util\JsonApiResponseAsserts;
use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Integration tests for CustomRouteController.
 *
 * Tests custom route handlers with real PostgreSQL database.
 */
final class CustomRouteControllerTest extends DoctrineIntegrationTestCase
{
    use JsonApiResponseAsserts;

    private CustomRouteController $controller;
    private CustomRouteRegistry $customRouteRegistry;
    private array $handlers = [];

    protected function getDatabaseUrl(): string
    {
        return $_ENV['DATABASE_URL_POSTGRES'] ?? 'postgresql://jsonapi:secret@postgres:5432/jsonapi_test?serverVersion=16&charset=utf8';
    }

    protected function getPlatform(): string
    {
        return 'postgresql';
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Set up routing
        $routes = new RouteCollection();
        foreach (['articles', 'categories', 'tags', 'category_synonyms', 'authors'] as $type) {
            $routes->add("jsonapi.{$type}.index", new Route("/api/{$type}"));
            $routes->add("jsonapi.{$type}.show", new Route("/api/{$type}/{id}"));
        }

        // Add relationship routes
        $routes->add('jsonapi.articles.relationships.author.show', new Route('/api/articles/{id}/relationships/author'));
        $routes->add('jsonapi.articles.relationships.tags.show', new Route('/api/articles/{id}/relationships/tags'));
        $routes->add('jsonapi.category_synonyms.relationships.category.show', new Route('/api/category_synonyms/{id}/relationships/category'));
        $routes->add('jsonapi.categories.relationships.parent.show', new Route('/api/categories/{id}/relationships/parent'));
        $routes->add('jsonapi.categories.relationships.children.show', new Route('/api/categories/{id}/relationships/children'));

        // Add related routes
        $routes->add('jsonapi.articles.related.author', new Route('/api/articles/{id}/author'));
        $routes->add('jsonapi.articles.related.tags', new Route('/api/articles/{id}/tags'));
        $routes->add('jsonapi.category_synonyms.related.category', new Route('/api/category_synonyms/{id}/category'));
        $routes->add('jsonapi.categories.related.parent', new Route('/api/categories/{id}/parent'));
        $routes->add('jsonapi.categories.related.children', new Route('/api/categories/{id}/children'));

        // Add custom routes
        $routes->add('jsonapi.categories.category_synonyms', new Route('/api/categories/{categoryId}/category_synonyms'));
        $routes->add('jsonapi.articles.publish', new Route('/api/articles/{id}/publish'));
        $routes->add('jsonapi.articles.create_with_tags', new Route('/api/articles/create-with-tags'));
        $routes->add('jsonapi.articles.custom_delete', new Route('/api/articles/{id}/custom-delete'));
        $routes->add('jsonapi.test.failing', new Route('/api/test/failing'));
        $routes->add('jsonapi.test.error_returning', new Route('/api/test/error-returning'));

        $context = new RequestContext();
        $context->setScheme('http');
        $context->setHost('localhost');

        $urlGenerator = new UrlGenerator($routes, $context);
        $linkGenerator = new LinkGenerator($urlGenerator);

        // Set up custom route registry
        $this->customRouteRegistry = new CustomRouteRegistry();

        // Register custom routes
        $this->customRouteRegistry->addRoute(new CustomRouteMetadata(
            name: 'jsonapi.categories.category_synonyms',
            path: '/api/categories/{categoryId}/category_synonyms',
            methods: ['GET'],
            handler: CategorySynonymsByCategoryHandler::class,
            controller: null,
            resourceType: 'category_synonyms',
            defaults: [],
            requirements: ['categoryId' => '\d+'],
            description: 'Get all synonyms for a specific category',
            priority: 10
        ));

        $this->customRouteRegistry->addRoute(new CustomRouteMetadata(
            name: 'jsonapi.articles.publish',
            path: '/api/articles/{id}/publish',
            methods: ['POST'],
            handler: PublishArticleHandler::class,
            controller: null,
            resourceType: 'articles',
            defaults: [],
            requirements: [],
            description: 'Publish an article',
            priority: 0
        ));

        $this->customRouteRegistry->addRoute(new CustomRouteMetadata(
            name: 'jsonapi.articles.create_with_tags',
            path: '/api/articles/create-with-tags',
            methods: ['POST'],
            handler: CreateArticleWithTagsHandler::class,
            controller: null,
            resourceType: 'articles',
            defaults: [],
            requirements: [],
            description: 'Create article with tags',
            priority: 0
        ));

        $this->customRouteRegistry->addRoute(new CustomRouteMetadata(
            name: 'jsonapi.articles.custom_delete',
            path: '/api/articles/{id}/custom-delete',
            methods: ['DELETE'],
            handler: DeleteArticleHandler::class,
            controller: null,
            resourceType: 'articles',
            defaults: [],
            requirements: [],
            description: 'Custom delete endpoint',
            priority: 0
        ));

        $this->customRouteRegistry->addRoute(new CustomRouteMetadata(
            name: 'jsonapi.test.failing',
            path: '/api/test/failing',
            methods: ['GET'],
            handler: FailingHandler::class,
            controller: null,
            resourceType: 'articles',
            defaults: [],
            requirements: [],
            description: 'Test handler that throws exception',
            priority: 0
        ));

        $this->customRouteRegistry->addRoute(new CustomRouteMetadata(
            name: 'jsonapi.test.error_returning',
            path: '/api/test/error-returning',
            methods: ['GET'],
            handler: ErrorReturningHandler::class,
            controller: null,
            resourceType: 'articles',
            defaults: [],
            requirements: [],
            description: 'Test handler that returns error',
            priority: 0
        ));

        // Create handler instances
        $this->handlers = [
            CategorySynonymsByCategoryHandler::class => new CategorySynonymsByCategoryHandler($this->em),
            PublishArticleHandler::class => new PublishArticleHandler($this->em),
            CreateArticleWithTagsHandler::class => new CreateArticleWithTagsHandler($this->em),
            DeleteArticleHandler::class => new DeleteArticleHandler($this->em),
            FailingHandler::class => new FailingHandler(),
            ErrorReturningHandler::class => new ErrorReturningHandler(),
        ];

        // Create handler locator
        $handlerLocator = new class ($this->handlers) implements ContainerInterface {
            public function __construct(private array $handlers)
            {
            }

            public function get(string $id): object
            {
                return $this->handlers[$id] ?? throw new \RuntimeException("Handler not found: {$id}");
            }

            public function has(string $id): bool
            {
                return isset($this->handlers[$id]);
            }
        };

        // Set up dependencies
        $operatorRegistry = new Registry([new EqualOperator()]);
        $filterHandlerRegistry = new FilterHandlerRegistry();
        $filterCompiler = new DoctrineFilterCompiler($operatorRegistry, $filterHandlerRegistry);
        $sortHandlerRegistry = new SortHandlerRegistry();
        $readMapper = new DefaultReadMapper();

        $repository = new GenericDoctrineRepository(
            $this->managerRegistry,
            $this->registry,
            $filterCompiler,
            $sortHandlerRegistry,
            $readMapper
        );

        $errorBuilder = new ErrorBuilder(true);
        $errorMapper = new ErrorMapper($errorBuilder);
        $paginationConfig = new PaginationConfig(defaultSize: 10, maxSize: 100);
        $sortingWhitelist = new SortingWhitelist($this->registry);
        $filteringWhitelist = new FilteringWhitelist($this->registry, $errorMapper);
        $filterParser = new FilterParser();

        $queryParser = new QueryParser(
            $this->registry,
            $paginationConfig,
            $sortingWhitelist,
            $filteringWhitelist,
            $errorMapper,
            $filterParser
        );

        $documentBuilder = new DocumentBuilder(
            $this->registry,
            $this->accessor,
            $linkGenerator,
            'always'
        );

        $contextFactory = new CustomRouteContextFactory(
            $this->customRouteRegistry,
            $this->registry,
            $repository,
            $queryParser,
            $errorMapper
        );

        $responseBuilder = new CustomRouteResponseBuilder(
            $documentBuilder,
            $linkGenerator,
            $errorBuilder
        );

        $handlerRegistry = new CustomRouteHandlerRegistry(
            $this->customRouteRegistry,
            $handlerLocator
        );

        $transactionManager = new DoctrineTransactionManager($this->managerRegistry);
        $eventDispatcher = new EventDispatcher();

        $this->controller = new CustomRouteController(
            $handlerRegistry,
            $contextFactory,
            $responseBuilder,
            $transactionManager,
            $eventDispatcher,
            $errorBuilder
        );
    }

    /**
     * Test 1: GET collection via custom route with NoTransaction attribute.
     */
    public function testGetCollectionViaCustomRoute(): void
    {
        // Create category
        $category = new Category();
        $category->setName('Technology');
        $this->em->persist($category);

        // Create synonyms
        $synonym1 = new CategorySynonym();
        $synonym1->setName('Tech');
        $synonym1->setCategory($category);
        $this->em->persist($synonym1);

        $synonym2 = new CategorySynonym();
        $synonym2->setName('IT');
        $synonym2->setCategory($category);
        $this->em->persist($synonym2);

        $this->em->flush();
        $categoryId = $category->getId();
        $this->em->clear();

        // Request custom route
        $request = Request::create("/api/categories/{$categoryId}/category_synonyms", 'GET');
        $request->attributes->set('_route_params', ['categoryId' => $categoryId]);

        $response = ($this->controller)($request, 'jsonapi.categories.category_synonyms');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(MediaType::JSON_API, $response->headers->get('Content-Type'));

        $document = $this->decode($response);

        self::assertIsArray($document['data']);
        self::assertCount(2, $document['data']);
        self::assertSame('category_synonyms', $document['data'][0]['type']);
        self::assertSame('category_synonyms', $document['data'][1]['type']);
    }

    /**
     * Test 2: POST to update resource via custom route (with transaction).
     */
    public function testPostToUpdateResourceViaCustomRoute(): void
    {
        // Create article
        $article = new Article();
        $article->setTitle('Draft Article');
        $article->setContent('Content');
        $this->em->persist($article);
        $this->em->flush();

        $articleId = $article->getId();
        $this->em->clear();

        // Publish article via custom route
        $request = Request::create("/api/articles/{$articleId}/publish", 'POST');
        $request->attributes->set('_route_params', ['id' => $articleId]);

        $response = ($this->controller)($request, 'jsonapi.articles.publish');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        self::assertSame('articles', $document['data']['type']);
        self::assertSame($articleId, $document['data']['id']);
        self::assertStringContainsString('[PUBLISHED]', $document['data']['attributes']['title']);

        // Verify in database
        $this->em->clear();
        $updatedArticle = $this->em->find(Article::class, $articleId);
        self::assertStringContainsString('[PUBLISHED]', $updatedArticle->getTitle());
    }

    /**
     * Test 3: Custom route with filtering.
     *
     * TDD: This test documents expected behavior for filtering support in custom routes.
     */
    public function testCustomRouteWithFiltering(): void
    {
        // Create category
        $category = new Category();
        $category->setName('Technology');
        $this->em->persist($category);

        // Create synonyms with different names
        $synonym1 = new CategorySynonym();
        $synonym1->setName('Tech');
        $synonym1->setCategory($category);
        $this->em->persist($synonym1);

        $synonym2 = new CategorySynonym();
        $synonym2->setName('IT');
        $synonym2->setCategory($category);
        $this->em->persist($synonym2);

        $synonym3 = new CategorySynonym();
        $synonym3->setName('Technology');
        $synonym3->setCategory($category);
        $this->em->persist($synonym3);

        $this->em->flush();
        $categoryId = $category->getId();
        $this->em->clear();

        // Request with filter
        $request = Request::create(
            "/api/categories/{$categoryId}/category_synonyms?filter[name][eq]=Tech",
            'GET'
        );
        $request->attributes->set('_route_params', ['categoryId' => $categoryId]);


        $response = ($this->controller)($request, 'jsonapi.categories.category_synonyms');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        // Should return only filtered results
        self::assertIsArray($document['data']);
        self::assertCount(1, $document['data']);
        self::assertSame('Tech', $document['data'][0]['attributes']['name']);
    }

    /**
     * Test 4: Custom route with sorting.
     *
     * TDD: This test documents expected behavior for sorting support in custom routes.
     */
    public function testCustomRouteWithSorting(): void
    {
        // Create category
        $category = new Category();
        $category->setName('Technology');
        $this->em->persist($category);

        // Create synonyms with different names (will be sorted alphabetically)
        $synonym1 = new CategorySynonym();
        $synonym1->setName('Zebra');
        $synonym1->setCategory($category);
        $this->em->persist($synonym1);

        $synonym2 = new CategorySynonym();
        $synonym2->setName('Alpha');
        $synonym2->setCategory($category);
        $this->em->persist($synonym2);

        $synonym3 = new CategorySynonym();
        $synonym3->setName('Beta');
        $synonym3->setCategory($category);
        $this->em->persist($synonym3);

        $this->em->flush();
        $categoryId = $category->getId();
        $this->em->clear();

        // Request with sorting
        $request = Request::create(
            "/api/categories/{$categoryId}/category_synonyms?sort=name",
            'GET'
        );
        $request->attributes->set('_route_params', ['categoryId' => $categoryId]);


        $response = ($this->controller)($request, 'jsonapi.categories.category_synonyms');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        // Should return sorted results
        self::assertIsArray($document['data']);
        self::assertCount(3, $document['data']);
        self::assertSame('Alpha', $document['data'][0]['attributes']['name']);
        self::assertSame('Beta', $document['data'][1]['attributes']['name']);
        self::assertSame('Zebra', $document['data'][2]['attributes']['name']);
    }

    /**
     * Test 5: DELETE via custom route (204 No Content).
     */
    public function testDeleteViaCustomRoute(): void
    {
        // Create article
        $article = new Article();
        $article->setTitle('Article to Delete');
        $article->setContent('Content');
        $this->em->persist($article);
        $this->em->flush();

        $articleId = $article->getId();
        $this->em->clear();

        // Delete via custom route
        $request = Request::create("/api/articles/{$articleId}/custom-delete", 'DELETE');
        $request->attributes->set('_route_params', ['id' => $articleId]);

        $response = ($this->controller)($request, 'jsonapi.articles.custom_delete');

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertEmpty($response->getContent());

        // Verify deleted from database
        $this->em->clear();
        $deletedArticle = $this->em->find(Article::class, $articleId);
        self::assertNull($deletedArticle);
    }

    /**
     * Test 6: Custom route with pagination.
     *
     * TDD: This test documents expected behavior for pagination support in custom routes.
     */
    public function testCustomRouteWithPagination(): void
    {
        // Create category
        $category = new Category();
        $category->setName('Technology');
        $this->em->persist($category);

        // Create 5 synonyms
        for ($i = 1; $i <= 5; $i++) {
            $synonym = new CategorySynonym();
            $synonym->setName("Synonym {$i}");
            $synonym->setCategory($category);
            $this->em->persist($synonym);
        }

        $this->em->flush();
        $categoryId = $category->getId();
        $this->em->clear();

        // Request with pagination
        $request = Request::create(
            "/api/categories/{$categoryId}/category_synonyms?page[size]=2&page[number]=1",
            'GET'
        );
        $request->attributes->set('_route_params', ['categoryId' => $categoryId]);


        $response = ($this->controller)($request, 'jsonapi.categories.category_synonyms');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        // Should return only 2 items (page size)
        self::assertIsArray($document['data']);
        self::assertCount(2, $document['data']);

        // Should have pagination links
        self::assertArrayHasKey('links', $document);
        self::assertArrayHasKey('meta', $document);

        // Verify pagination meta (matches standard collection format)
        self::assertSame(5, $document['meta']['total']);
        self::assertSame(1, $document['meta']['page']);
        self::assertSame(2, $document['meta']['size']);
    }

    /**
     * Test 7: Handler throws exception - converted to JSON:API error.
     */
    public function testHandlerThrowsException(): void
    {
        $request = Request::create('/api/test/failing', 'GET');

        $this->expectException(\AlexFigures\Symfony\Http\Exception\JsonApiHttpException::class);
        $this->expectExceptionMessage('An unexpected error occurred');

        ($this->controller)($request, 'jsonapi.test.failing');
    }

    /**
     * Test 8: Handler returns error result - transaction rolled back.
     */
    public function testHandlerReturnsErrorResult(): void
    {
        $request = Request::create('/api/test/error-returning', 'GET');
        $response = ($this->controller)($request, 'jsonapi.test.error_returning');

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $document = $this->decode($response);

        self::assertArrayHasKey('errors', $document);
        self::assertCount(1, $document['errors']);
        self::assertSame('400', $document['errors'][0]['status']);
    }

    /**
     * Test 9: Custom route with sparse fieldsets.
     */
    public function testCustomRouteWithSparseFieldsets(): void
    {
        // Create category
        $category = new Category();
        $category->setName('Technology');
        $this->em->persist($category);

        // Create synonyms
        $synonym1 = new CategorySynonym();
        $synonym1->setName('Tech');
        $synonym1->setCategory($category);
        $this->em->persist($synonym1);

        $this->em->flush();
        $categoryId = $category->getId();
        $this->em->clear();

        // Request with sparse fieldsets
        $request = Request::create(
            "/api/categories/{$categoryId}/category_synonyms?fields[category_synonyms]=name",
            'GET'
        );
        $request->attributes->set('_route_params', ['categoryId' => $categoryId]);

        $response = ($this->controller)($request, 'jsonapi.categories.category_synonyms');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        // Should only have 'name' attribute
        self::assertArrayHasKey('name', $document['data'][0]['attributes']);
        self::assertArrayNotHasKey('createdAt', $document['data'][0]['attributes']);
    }

    /**
     * Test 10: Custom route with include.
     */
    public function testCustomRouteWithInclude(): void
    {
        // Create category
        $category = new Category();
        $category->setName('Technology');
        $this->em->persist($category);

        // Create synonym
        $synonym = new CategorySynonym();
        $synonym->setName('Tech');
        $synonym->setCategory($category);
        $this->em->persist($synonym);

        $this->em->flush();
        $categoryId = $category->getId();
        $this->em->clear();

        // Request with include
        $request = Request::create(
            "/api/categories/{$categoryId}/category_synonyms?include=category",
            'GET'
        );
        $request->attributes->set('_route_params', ['categoryId' => $categoryId]);

        $response = ($this->controller)($request, 'jsonapi.categories.category_synonyms');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        // Should have included category
        self::assertArrayHasKey('included', $document);
        self::assertCount(1, $document['included']);
        self::assertSame('categories', $document['included'][0]['type']);
        self::assertSame($categoryId, $document['included'][0]['id']);
    }

    /**
     * Test 11: Custom route returns empty collection.
     */
    public function testCustomRouteReturnsEmptyCollection(): void
    {
        // Create category without synonyms
        $category = new Category();
        $category->setName('Empty Category');
        $this->em->persist($category);
        $this->em->flush();

        $categoryId = $category->getId();
        $this->em->clear();

        // Request custom route
        $request = Request::create("/api/categories/{$categoryId}/category_synonyms", 'GET');
        $request->attributes->set('_route_params', ['categoryId' => $categoryId]);

        $response = ($this->controller)($request, 'jsonapi.categories.category_synonyms');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        self::assertIsArray($document['data']);
        self::assertCount(0, $document['data']);
    }
}
