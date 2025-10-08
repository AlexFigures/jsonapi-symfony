<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Integration\CustomRoute;

use JsonApi\Symfony\Contract\Data\ResourceRepository;
use JsonApi\Symfony\Contract\Data\Slice;
use JsonApi\Symfony\Contract\Tx\TransactionManager;
use JsonApi\Symfony\CustomRoute\Context\CustomRouteContext;
use JsonApi\Symfony\CustomRoute\Context\CustomRouteContextFactory;
use JsonApi\Symfony\CustomRoute\Controller\CustomRouteController;
use JsonApi\Symfony\CustomRoute\Handler\CustomRouteHandlerInterface;
use JsonApi\Symfony\CustomRoute\Handler\CustomRouteHandlerRegistry;
use JsonApi\Symfony\CustomRoute\Response\CustomRouteResponseBuilder;
use JsonApi\Symfony\CustomRoute\Result\CustomRouteResult;
use JsonApi\Symfony\Events\ResourceChangedEvent;
use JsonApi\Symfony\Http\Document\DocumentBuilder;
use JsonApi\Symfony\Http\Error\ErrorBuilder;
use JsonApi\Symfony\Http\Error\ErrorMapper;
use JsonApi\Symfony\Http\Link\LinkGenerator;
use JsonApi\Symfony\Http\Request\QueryParser;
use JsonApi\Symfony\Query\Criteria;
use JsonApi\Symfony\Resource\Metadata\CustomRouteMetadata;
use JsonApi\Symfony\Resource\Metadata\ResourceMetadata;
use JsonApi\Symfony\Resource\Registry\CustomRouteRegistry;
use JsonApi\Symfony\Resource\Registry\ResourceRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

/**
 * Integration tests for Phase 3 advanced features:
 * - Sparse fieldsets support
 * - Includes support
 * - Pagination support
 * - Resource pre-loading
 * - Event dispatching
 *
 * @covers \JsonApi\Symfony\CustomRoute\Context\CustomRouteContextFactory
 * @covers \JsonApi\Symfony\CustomRoute\Response\CustomRouteResponseBuilder
 * @covers \JsonApi\Symfony\CustomRoute\Controller\CustomRouteController
 */
final class AdvancedFeaturesTest extends TestCase
{
    private CustomRouteController $controller;
    private array $articles = [];
    private array $authors = [];
    private array $dispatchedEvents = [];
    private DocumentBuilder $documentBuilder;
    private LinkGenerator $linkGenerator;

    protected function setUp(): void
    {
        // Create test data
        $this->authors = [
            $this->createAuthor('1', 'John Doe', 'john@example.com'),
            $this->createAuthor('2', 'Jane Smith', 'jane@example.com'),
        ];

        $this->articles = [
            $this->createArticle('1', 'First Article', 'Content 1', $this->authors[0]),
            $this->createArticle('2', 'Second Article', 'Content 2', $this->authors[1]),
            $this->createArticle('3', 'Third Article', 'Content 3', $this->authors[0]),
        ];

        // Set up the complete infrastructure
        $this->controller = $this->createController();
    }

    public function testSparseFieldsetsForResourceResponse(): void
    {
        // Request only title field (not body)
        $request = Request::create('/api/articles/1?fields[articles]=title', 'GET');
        $request->attributes->set('_route_params', ['id' => '1']);

        $response = ($this->controller)($request, 'articles.get');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertArrayHasKey('data', $data);
        self::assertArrayHasKey('attributes', $data['data']);
        
        // Should have title
        self::assertArrayHasKey('title', $data['data']['attributes']);
        self::assertSame('First Article', $data['data']['attributes']['title']);
        
        // Should NOT have body (filtered out by sparse fieldsets)
        self::assertArrayNotHasKey('body', $data['data']['attributes']);
    }

    public function testIncludesForResourceResponse(): void
    {
        // Request article with author included
        $request = Request::create('/api/articles/1?include=author', 'GET');
        $request->attributes->set('_route_params', ['id' => '1']);

        $response = ($this->controller)($request, 'articles.get');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        
        // Should have included section with author
        self::assertArrayHasKey('included', $data);
        self::assertCount(1, $data['included']);
        self::assertSame('authors', $data['included'][0]['type']);
        self::assertSame('1', $data['included'][0]['id']);
        self::assertSame('John Doe', $data['included'][0]['attributes']['name']);
    }

    public function testPaginationLinksForCollectionResponse(): void
    {
        // Request page 2 with size 1
        $request = Request::create('/api/articles?page[number]=2&page[size]=1', 'GET');
        $request->attributes->set('_route_params', []);

        $response = ($this->controller)($request, 'articles.list');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        
        // Should have pagination links
        self::assertArrayHasKey('links', $data);
        self::assertArrayHasKey('self', $data['links']);
        self::assertArrayHasKey('first', $data['links']);
        self::assertArrayHasKey('last', $data['links']);
        self::assertArrayHasKey('prev', $data['links']);
        self::assertArrayHasKey('next', $data['links']);
        
        // Should have pagination meta
        self::assertArrayHasKey('meta', $data);
        self::assertSame(3, $data['meta']['total']);
        self::assertSame(2, $data['meta']['page']);
        self::assertSame(1, $data['meta']['size']);
        
        // Should have correct number of items
        self::assertCount(1, $data['data']);
    }

    public function testResourcePreLoadingForRoutesWithIdParameter(): void
    {
        $request = Request::create('/api/articles/1/publish', 'POST');
        $request->attributes->set('_route_params', ['id' => '1']);

        $response = ($this->controller)($request, 'articles.publish');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        
        // Verify the resource was pre-loaded and modified by the handler
        self::assertArrayHasKey('data', $data);
        self::assertSame('1', $data['data']['id']);
        self::assertTrue($this->articles[0]->published);
    }

    public function testResourcePreLoadingThrowsNotFoundForInvalidId(): void
    {
        $request = Request::create('/api/articles/999/publish', 'POST');
        $request->attributes->set('_route_params', ['id' => '999']);

        $this->expectException(\JsonApi\Symfony\Http\Exception\NotFoundException::class);
        $this->expectExceptionMessage('Resource "articles" with id "999" not found');

        ($this->controller)($request, 'articles.publish');
    }

    public function testEventDispatchingForCreatedResource(): void
    {
        $request = Request::create('/api/articles', 'POST');
        $request->attributes->set('_route_params', []);
        $request->headers->set('Content-Type', 'application/json');
        $request->initialize([], [], [], [], [], [], json_encode([
            'title' => 'New Article',
            'body' => 'New content',
        ]));

        $response = ($this->controller)($request, 'articles.create');

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        // Verify event was dispatched
        self::assertCount(1, $this->dispatchedEvents);
        $event = $this->dispatchedEvents[0];
        self::assertInstanceOf(ResourceChangedEvent::class, $event);
        self::assertSame('articles', $event->type);
        self::assertSame('create', $event->operation);
    }

    public function testEventDispatchingForUpdatedResource(): void
    {
        $request = Request::create('/api/articles/1/publish', 'POST');
        $request->attributes->set('_route_params', ['id' => '1']);

        $response = ($this->controller)($request, 'articles.publish');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        // Verify event was dispatched
        self::assertCount(1, $this->dispatchedEvents);
        $event = $this->dispatchedEvents[0];
        self::assertInstanceOf(ResourceChangedEvent::class, $event);
        self::assertSame('articles', $event->type);
        self::assertSame('1', $event->id);
        self::assertSame('update', $event->operation);
    }

    public function testEventDispatchingForDeletedResource(): void
    {
        $request = Request::create('/api/articles/1', 'DELETE');
        $request->attributes->set('_route_params', ['id' => '1']);

        $response = ($this->controller)($request, 'articles.delete');

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        // Verify event was dispatched
        self::assertCount(1, $this->dispatchedEvents);
        $event = $this->dispatchedEvents[0];
        self::assertInstanceOf(ResourceChangedEvent::class, $event);
        self::assertSame('articles', $event->type);
        self::assertSame('1', $event->id);
        self::assertSame('delete', $event->operation);
    }

    public function testEventDispatchingForUpdateUsesRouteParameter(): void
    {
        // This test verifies the fix for the regression where update events
        // were not dispatched when handlers returned DTOs without id property
        $request = Request::create('/api/articles/123/publish', 'POST');
        $request->attributes->set('_route_params', ['id' => '123']);

        $response = ($this->controller)($request, 'articles.publish');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        // Verify event was dispatched with correct ID from route parameter
        self::assertCount(1, $this->dispatchedEvents);
        $event = $this->dispatchedEvents[0];
        self::assertInstanceOf(ResourceChangedEvent::class, $event);
        self::assertSame('articles', $event->type);
        self::assertSame('123', $event->id); // Should use route param, not extracted from result
        self::assertSame('update', $event->operation);
    }

    public function testEventDispatchingWorksWithDtoResponse(): void
    {
        // This test verifies handlers can return DTOs/value objects without id property
        // and events are still dispatched correctly using the route parameter
        $request = Request::create('/api/articles/456/update-dto', 'PATCH');
        $request->attributes->set('_route_params', ['id' => '456']);

        $response = ($this->controller)($request, 'articles.update-dto');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        // Verify event was dispatched even though DTO has no id property
        self::assertCount(1, $this->dispatchedEvents);
        $event = $this->dispatchedEvents[0];
        self::assertInstanceOf(ResourceChangedEvent::class, $event);
        self::assertSame('articles', $event->type);
        self::assertSame('456', $event->id); // Uses route param since DTO has no id
        self::assertSame('update', $event->operation);
    }

    private function createController(): CustomRouteController
    {
        // Create custom route registry with test routes
        $customRouteRegistry = new CustomRouteRegistry([
            new CustomRouteMetadata(
                name: 'articles.get',
                path: '/api/articles/{id}',
                methods: ['GET'],
                handler: GetArticleHandler::class,
                controller: null,
                resourceType: 'articles',
                defaults: [],
                requirements: [],
                description: 'Get an article',
                priority: 0
            ),
            new CustomRouteMetadata(
                name: 'articles.list',
                path: '/api/articles',
                methods: ['GET'],
                handler: ListArticlesHandler::class,
                controller: null,
                resourceType: 'articles',
                defaults: [],
                requirements: [],
                description: 'List articles',
                priority: 0
            ),
            new CustomRouteMetadata(
                name: 'articles.publish',
                path: '/api/articles/{id}/publish',
                methods: ['POST'],
                handler: PublishArticleHandler::class,
                controller: null,
                resourceType: 'articles',
                defaults: [],
                requirements: [],
                description: 'Publish an article',
                priority: 0
            ),
            new CustomRouteMetadata(
                name: 'articles.create',
                path: '/api/articles',
                methods: ['POST'],
                handler: CreateArticleHandler::class,
                controller: null,
                resourceType: 'articles',
                defaults: [],
                requirements: [],
                description: 'Create an article',
                priority: 0
            ),
            new CustomRouteMetadata(
                name: 'articles.delete',
                path: '/api/articles/{id}',
                methods: ['DELETE'],
                handler: DeleteArticleHandler::class,
                controller: null,
                resourceType: 'articles',
                defaults: [],
                requirements: [],
                description: 'Delete an article',
                priority: 0
            ),
            new CustomRouteMetadata(
                name: 'articles.update-dto',
                path: '/api/articles/{id}/update-dto',
                methods: ['PATCH'],
                handler: \JsonApi\Symfony\Tests\Fixtures\CustomRoute\UpdateArticleWithDtoHandler::class,
                controller: null,
                resourceType: 'articles',
                defaults: [],
                requirements: [],
                description: 'Update article returning DTO',
                priority: 0
            ),
        ]);

        // Create handlers
        $handlers = [
            GetArticleHandler::class => new GetArticleHandler(),
            ListArticlesHandler::class => new ListArticlesHandler($this->articles),
            PublishArticleHandler::class => new PublishArticleHandler(),
            CreateArticleHandler::class => new CreateArticleHandler($this->articles),
            DeleteArticleHandler::class => new DeleteArticleHandler($this->articles),
            \JsonApi\Symfony\Tests\Fixtures\CustomRoute\UpdateArticleWithDtoHandler::class => new \JsonApi\Symfony\Tests\Fixtures\CustomRoute\UpdateArticleWithDtoHandler(),
        ];

        $handlerLocator = $this->createHandlerLocator($handlers);

        // Create resource metadata
        $articleMetadata = $this->createArticleMetadata();
        $authorMetadata = $this->createAuthorMetadata();
        $resourceRegistry = new ResourceRegistry([$articleMetadata, $authorMetadata]);

        // Create repository
        $repository = $this->createMockRepository();

        // Create query parser
        $queryParser = $this->createQueryParser();

        // Create error builder and mapper
        $errorBuilder = new ErrorBuilder(useDefaultTitleMap: true);
        $errorMapper = new ErrorMapper($errorBuilder);

        // Create context factory
        $contextFactory = new CustomRouteContextFactory(
            $customRouteRegistry,
            $resourceRegistry,
            $repository,
            $queryParser,
            $errorMapper
        );

        // Create document builder and link generator
        $this->linkGenerator = $this->createLinkGenerator();
        $this->documentBuilder = new DocumentBuilder(
            $resourceRegistry,
            new PropertyAccessor(),
            $this->linkGenerator,
            'when_included'
        );

        // Create response builder
        $responseBuilder = new CustomRouteResponseBuilder(
            $this->documentBuilder,
            $this->linkGenerator,
            $errorBuilder
        );

        // Create handler registry
        $handlerRegistry = new CustomRouteHandlerRegistry(
            $customRouteRegistry,
            $handlerLocator
        );

        // Create transaction manager
        $transactionManager = $this->createMockTransactionManager();

        // Create event dispatcher that captures events
        $eventDispatcher = $this->createEventDispatcher();

        return new CustomRouteController(
            $handlerRegistry,
            $contextFactory,
            $responseBuilder,
            $transactionManager,
            $eventDispatcher,
            $errorBuilder,
            new NullLogger()
        );
    }

    private function createHandlerLocator(array $handlers): ContainerInterface
    {
        return new class($handlers) implements ContainerInterface {
            public function __construct(private array $handlers) {}

            public function get(string $id): object
            {
                return $this->handlers[$id] ?? throw new \RuntimeException("Handler $id not found");
            }

            public function has(string $id): bool
            {
                return isset($this->handlers[$id]);
            }
        };
    }

    private function createMockRepository(): ResourceRepository
    {
        $articles = &$this->articles;
        $authors = &$this->authors;

        return new class($articles, $authors) implements ResourceRepository {
            public function __construct(
                private array &$articles,
                private array &$authors
            ) {}

            public function findOne(string $type, string $id, Criteria $criteria): ?object
            {
                $items = $type === 'articles' ? $this->articles : $this->authors;

                foreach ($items as $item) {
                    if ($item->id === $id) {
                        return $item;
                    }
                }
                return null;
            }

            public function findCollection(string $type, Criteria $criteria): Slice
            {
                $items = $type === 'articles' ? $this->articles : $this->authors;
                $pageNumber = $criteria->pagination->number;
                $pageSize = $criteria->pagination->size;

                $offset = ($pageNumber - 1) * $pageSize;
                $pageItems = array_slice($items, $offset, $pageSize);

                return new Slice(
                    items: $pageItems,
                    pageNumber: $pageNumber,
                    pageSize: $pageSize,
                    totalItems: count($items)
                );
            }
        };
    }

    private function createQueryParser(): QueryParser
    {
        // QueryParser constructor signature: (ResourceRegistryInterface, FilterParser, SortParser, PaginationParser, FieldsParser, IncludeParser)
        // For testing, we'll create a stub that returns basic criteria
        return $this->createStub(QueryParser::class);
    }

    private function createLinkGenerator(): LinkGenerator
    {
        $routes = new RouteCollection();
        $context = new RequestContext();
        $urlGenerator = new UrlGenerator($routes, $context);

        return new LinkGenerator($urlGenerator);
    }

    private function createMockTransactionManager(): TransactionManager
    {
        return new class implements TransactionManager {
            public function transactional(callable $callback): mixed
            {
                return $callback();
            }
        };
    }

    private function createEventDispatcher(): EventDispatcher
    {
        $events = &$this->dispatchedEvents;

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ResourceChangedEvent::class, function (ResourceChangedEvent $event) use (&$events) {
            $events[] = $event;
        });

        return $dispatcher;
    }

    private function createArticleMetadata(): ResourceMetadata
    {
        return new ResourceMetadata(
            type: 'articles',
            class: \stdClass::class,
            attributes: [],
            relationships: []
        );
    }

    private function createAuthorMetadata(): ResourceMetadata
    {
        return new ResourceMetadata(
            type: 'authors',
            class: \stdClass::class,
            attributes: [],
            relationships: []
        );
    }

    private function createArticle(string $id, string $title, string $body, object $author): object
    {
        return new class($id, $title, $body, $author) {
            public bool $published = false;
            public ?\DateTimeImmutable $publishedAt = null;

            public function __construct(
                public string $id,
                public string $title,
                public string $body,
                public object $author
            ) {}
        };
    }

    private function createAuthor(string $id, string $name, string $email): object
    {
        return new class($id, $name, $email) {
            public function __construct(
                public string $id,
                public string $name,
                public string $email
            ) {}
        };
    }
}

// Test handlers

final class GetArticleHandler implements CustomRouteHandlerInterface
{
    public function handle(CustomRouteContext $context): CustomRouteResult
    {
        return CustomRouteResult::resource($context->getResource());
    }
}

final class ListArticlesHandler implements CustomRouteHandlerInterface
{
    public function __construct(private array $articles) {}

    public function handle(CustomRouteContext $context): CustomRouteResult
    {
        return CustomRouteResult::collection($this->articles, count($this->articles));
    }
}

final class PublishArticleHandler implements CustomRouteHandlerInterface
{
    public function handle(CustomRouteContext $context): CustomRouteResult
    {
        $article = $context->getResource();
        $article->published = true;
        $article->publishedAt = new \DateTimeImmutable();

        return CustomRouteResult::resource($article);
    }
}

final class CreateArticleHandler implements CustomRouteHandlerInterface
{
    public function __construct(private array &$articles) {}

    public function handle(CustomRouteContext $context): CustomRouteResult
    {
        $body = $context->getBody();
        $newId = (string) (count($this->articles) + 1);

        $article = new class($newId, $body['title'], $body['body']) {
            public bool $published = false;

            public function __construct(
                public string $id,
                public string $title,
                public string $body
            ) {}
        };

        $this->articles[] = $article;

        return CustomRouteResult::created($article);
    }
}

final class DeleteArticleHandler implements CustomRouteHandlerInterface
{
    public function __construct(private array &$articles) {}

    public function handle(CustomRouteContext $context): CustomRouteResult
    {
        $id = $context->getParam('id');

        foreach ($this->articles as $key => $article) {
            if ($article->id === $id) {
                unset($this->articles[$key]);
                break;
            }
        }

        return CustomRouteResult::noContent();
    }
}

