<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Functional;

use AlexFigures\Symfony\Atomic\AtomicConfig;
use AlexFigures\Symfony\Atomic\Execution\AtomicTransaction;
use AlexFigures\Symfony\Atomic\Execution\Handlers\AddHandler;
use AlexFigures\Symfony\Atomic\Execution\Handlers\RelationshipOps;
use AlexFigures\Symfony\Atomic\Execution\Handlers\RemoveHandler;
use AlexFigures\Symfony\Atomic\Execution\Handlers\UpdateHandler;
use AlexFigures\Symfony\Atomic\Execution\OperationDispatcher;
use AlexFigures\Symfony\Atomic\Parser\AtomicRequestParser;
use AlexFigures\Symfony\Atomic\Result\ResultBuilder;
use AlexFigures\Symfony\Atomic\Validation\AtomicValidator;
use AlexFigures\Symfony\Bridge\Symfony\Controller\AtomicController;
use AlexFigures\Symfony\Bridge\Symfony\Negotiation\ChannelScopeMatcher;
use AlexFigures\Symfony\Bridge\Symfony\Negotiation\ConfigMediaTypePolicyProvider;
use AlexFigures\Symfony\Contract\Data\ResourceProcessor;
use AlexFigures\Symfony\Contract\Data\ResourceRepository;
use AlexFigures\Symfony\Contract\Tx\TransactionManager;
use AlexFigures\Symfony\Http\Controller\CollectionController;
use AlexFigures\Symfony\Http\Controller\CreateResourceController;
use AlexFigures\Symfony\Http\Controller\DeleteResourceController;
use AlexFigures\Symfony\Http\Controller\RelatedController;
use AlexFigures\Symfony\Http\Controller\RelationshipGetController;
use AlexFigures\Symfony\Http\Controller\RelationshipWriteController;
use AlexFigures\Symfony\Http\Controller\ResourceController;
use AlexFigures\Symfony\Http\Controller\UpdateResourceController;
use AlexFigures\Symfony\Http\Document\DocumentBuilder;
use AlexFigures\Symfony\Http\Error\CorrelationIdProvider;
use AlexFigures\Symfony\Http\Error\ErrorBuilder;
use AlexFigures\Symfony\Http\Error\ErrorMapper;
use AlexFigures\Symfony\Http\Error\JsonApiExceptionListener;
use AlexFigures\Symfony\Http\Link\LinkGenerator;
use AlexFigures\Symfony\Http\Negotiation\MediaType;
use AlexFigures\Symfony\Http\Negotiation\MediaTypeNegotiator;
use AlexFigures\Symfony\Http\Negotiation\MediaTypePolicyProviderInterface;
use AlexFigures\Symfony\Http\Relationship\LinkageBuilder;
use AlexFigures\Symfony\Http\Relationship\WriteRelationshipsResponseConfig;
use AlexFigures\Symfony\Http\Request\PaginationConfig;
use AlexFigures\Symfony\Http\Request\QueryParser;
use AlexFigures\Symfony\Http\Request\SortingWhitelist;
use AlexFigures\Symfony\Http\Validation\ConstraintViolationMapper;
use AlexFigures\Symfony\Http\Write\ChangeSetFactory;
use AlexFigures\Symfony\Http\Write\InputDocumentValidator;
use AlexFigures\Symfony\Http\Write\RelationshipDocumentValidator;
use AlexFigures\Symfony\Http\Write\WriteConfig;
use AlexFigures\Symfony\Resource\Metadata\ResourceMetadata;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistry;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
use AlexFigures\Symfony\Resource\Relationship\RelationshipResolver;
use AlexFigures\Symfony\Tests\Fixtures\InMemory\InMemoryExistenceChecker;
use AlexFigures\Symfony\Tests\Fixtures\InMemory\InMemoryPersister;
use AlexFigures\Symfony\Tests\Fixtures\InMemory\InMemoryRelationshipReader;
use AlexFigures\Symfony\Tests\Fixtures\InMemory\InMemoryRelationshipResolver;
use AlexFigures\Symfony\Tests\Fixtures\InMemory\InMemoryRelationshipUpdater;
use AlexFigures\Symfony\Tests\Fixtures\InMemory\InMemoryRepository;
use AlexFigures\Symfony\Tests\Fixtures\InMemory\InMemoryTransactionManager;
use AlexFigures\Symfony\Tests\Fixtures\Model\Article;
use AlexFigures\Symfony\Tests\Fixtures\Model\Author;
use AlexFigures\Symfony\Tests\Fixtures\Model\Tag;
use AlexFigures\Symfony\Tests\Util\JsonApiResponseAsserts;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

abstract class JsonApiTestCase extends TestCase
{
    use JsonApiResponseAsserts;

    private ?ResourceRegistryInterface $registry = null;
    private ?ResourceRepository $repository = null;
    private ?QueryParser $parser = null;
    private ?DocumentBuilder $document = null;
    private ?CollectionController $collectionController = null;
    private ?ResourceController $resourceController = null;
    private ?PropertyAccessorInterface $accessor = null;
    private ?CreateResourceController $createController = null;
    private ?UpdateResourceController $updateController = null;
    private ?DeleteResourceController $deleteController = null;
    private ?ResourceProcessor $persister = null;
    private ?TransactionManager $transactionManager = null;
    private ?RelatedController $relatedController = null;
    private ?RelationshipGetController $relationshipGetController = null;
    private ?RelationshipWriteController $relationshipWriteController = null;
    private ?AtomicController $atomicController = null;
    private ?ErrorMapper $errorMapper = null;
    private ?ConstraintViolationMapper $violationMapper = null;
    private ?LinkGenerator $linkGenerator = null;
    private ?WriteConfig $writeConfig = null;
    private ?ChangeSetFactory $changeSetFactory = null;
    private ?EventDispatcherInterface $eventDispatcher = null;
    private ?RelationshipResolver $relationshipResolver = null;
    private ?MediaTypePolicyProviderInterface $mediaTypePolicyProvider = null;

    protected function collectionController(): CollectionController
    {
        $this->boot();

        \assert($this->collectionController instanceof CollectionController);

        return $this->collectionController;
    }

    protected function resourceController(): ResourceController
    {
        $this->boot();

        \assert($this->resourceController instanceof ResourceController);

        return $this->resourceController;
    }

    protected function createController(): CreateResourceController
    {
        $this->boot();

        \assert($this->createController instanceof CreateResourceController);

        return $this->createController;
    }

    protected function updateController(): UpdateResourceController
    {
        $this->boot();

        \assert($this->updateController instanceof UpdateResourceController);

        return $this->updateController;
    }

    protected function deleteController(): DeleteResourceController
    {
        $this->boot();

        \assert($this->deleteController instanceof DeleteResourceController);

        return $this->deleteController;
    }

    protected function relatedController(): RelatedController
    {
        $this->boot();

        \assert($this->relatedController instanceof RelatedController);

        return $this->relatedController;
    }

    protected function relationshipGetController(): RelationshipGetController
    {
        $this->boot();

        \assert($this->relationshipGetController instanceof RelationshipGetController);

        return $this->relationshipGetController;
    }

    protected function relationshipWriteController(): RelationshipWriteController
    {
        $this->boot();

        \assert($this->relationshipWriteController instanceof RelationshipWriteController);

        return $this->relationshipWriteController;
    }

    protected function atomicController(): AtomicController
    {
        $this->boot();

        \assert($this->atomicController instanceof AtomicController);

        return $this->atomicController;
    }

    protected function linkGenerator(): LinkGenerator
    {
        $this->boot();

        \assert($this->linkGenerator instanceof LinkGenerator);

        return $this->linkGenerator;
    }

    protected function writeConfig(): WriteConfig
    {
        $this->boot();

        \assert($this->writeConfig instanceof WriteConfig);

        return $this->writeConfig;
    }

    protected function changeSetFactory(): ChangeSetFactory
    {
        $this->boot();

        \assert($this->changeSetFactory instanceof ChangeSetFactory);

        return $this->changeSetFactory;
    }

    protected function errorMapper(): ErrorMapper
    {
        $this->boot();

        \assert($this->errorMapper instanceof ErrorMapper);

        return $this->errorMapper;
    }

    protected function violationMapper(): ConstraintViolationMapper
    {
        $this->boot();

        \assert($this->violationMapper instanceof ConstraintViolationMapper);

        return $this->violationMapper;
    }

    protected function handleException(Request $request, \Throwable $throwable, bool $exposeDebugMeta = false, string $correlationId = '00000000-0000-4000-8000-000000000000'): Response
    {
        $listener = new JsonApiExceptionListener(
            $this->errorMapper(),
            new class ($correlationId) extends CorrelationIdProvider {
                public function __construct(private string $id)
                {
                }

                public function generate(): string
                {
                    return $this->id;
                }
            },
            $exposeDebugMeta,
            true,
        );

        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $throwable);
        $listener->onKernelException($event);

        $response = $event->getResponse();
        \assert($response instanceof Response);

        return $response;
    }

    protected function registry(): ResourceRegistryInterface
    {
        $this->boot();

        \assert($this->registry instanceof ResourceRegistryInterface);

        return $this->registry;
    }

    protected function repository(): ResourceRepository
    {
        $this->boot();

        \assert($this->repository instanceof ResourceRepository);

        return $this->repository;
    }

    protected function parser(): QueryParser
    {
        $this->boot();

        \assert($this->parser instanceof QueryParser);

        return $this->parser;
    }

    protected function documentBuilder(): DocumentBuilder
    {
        $this->boot();

        \assert($this->document instanceof DocumentBuilder);

        return $this->document;
    }

    protected function propertyAccessor(): PropertyAccessorInterface
    {
        $this->boot();

        \assert($this->accessor instanceof PropertyAccessorInterface);

        return $this->accessor;
    }

    protected function persister(): ResourceProcessor
    {
        $this->boot();

        \assert($this->persister instanceof ResourceProcessor);

        return $this->persister;
    }

    protected function transactionManager(): TransactionManager
    {
        $this->boot();

        \assert($this->transactionManager instanceof TransactionManager);

        return $this->transactionManager;
    }

    protected function eventDispatcher(): EventDispatcherInterface
    {
        $this->boot();

        \assert($this->eventDispatcher instanceof EventDispatcherInterface);

        return $this->eventDispatcher;
    }

    protected function relationshipResolver(): RelationshipResolver
    {
        $this->boot();

        \assert($this->relationshipResolver instanceof RelationshipResolver);

        return $this->relationshipResolver;
    }

    protected function mediaTypePolicyProvider(): MediaTypePolicyProviderInterface
    {
        $this->boot();

        \assert($this->mediaTypePolicyProvider instanceof MediaTypePolicyProviderInterface);

        return $this->mediaTypePolicyProvider;
    }

    private function boot(): void
    {
        if ($this->collectionController !== null) {
            return;
        }

        $registry = new ResourceRegistry([
            Article::class,
            Author::class,
            Tag::class,
        ]);

        $pagination = new PaginationConfig(defaultSize: 25, maxSize: 100);
        $sorting = new SortingWhitelist($registry);

        $errorBuilder = new ErrorBuilder(true);
        $errorMapper = new ErrorMapper($errorBuilder);
        $violationMapper = new ConstraintViolationMapper($registry, $errorMapper);
        $filtering = new \AlexFigures\Symfony\Http\Request\FilteringWhitelist($registry, $errorMapper);

        $filterParser = new \AlexFigures\Symfony\Filter\Parser\FilterParser();
        $parser = new QueryParser($registry, $pagination, $sorting, $filtering, $errorMapper, $filterParser);

        $routes = new RouteCollection();
        $routes->add('jsonapi.collection', new Route('/api/{type}'));
        $routes->add('jsonapi.resource', new Route('/api/{type}/{id}'));
        $routes->add('jsonapi.related', new Route('/api/{type}/{id}/{rel}'));
        $routes->add('jsonapi.relationship.get', new Route('/api/{type}/{id}/relationships/{rel}'));
        $routes->add('jsonapi.relationship.write', new Route('/api/{type}/{id}/relationships/{rel}'));

        // Add type-specific routes for LinkGenerator
        foreach (['articles', 'authors', 'tags'] as $type) {
            $routes->add("jsonapi.{$type}.index", new Route("/api/{$type}"));
            $routes->add("jsonapi.{$type}.show", new Route("/api/{$type}/{id}"));
            $routes->add("jsonapi.{$type}.related.author", new Route("/api/{$type}/{id}/author"));
            $routes->add("jsonapi.{$type}.related.tags", new Route("/api/{$type}/{id}/tags"));
            $routes->add("jsonapi.{$type}.relationships.author.show", new Route("/api/{$type}/{id}/relationships/author"));
            $routes->add("jsonapi.{$type}.relationships.tags.show", new Route("/api/{$type}/{id}/relationships/tags"));
        }

        $context = new RequestContext();
        $context->setScheme('http');
        $context->setHost('localhost');

        $urlGenerator = new UrlGenerator($routes, $context);
        $linkGenerator = new LinkGenerator($urlGenerator);
        $this->linkGenerator = $linkGenerator;
        $accessor = PropertyAccess::createPropertyAccessor();
        $document = new DocumentBuilder($registry, $accessor, $linkGenerator, 'always');
        $repository = new InMemoryRepository($registry, $accessor);
        $writeConfig = new WriteConfig(true, [
            'authors' => true,
        ]);
        $this->writeConfig = $writeConfig;
        $validator = new InputDocumentValidator($registry, $writeConfig, $errorMapper);
        $changeSetFactory = new ChangeSetFactory($registry);
        $this->changeSetFactory = $changeSetFactory;
        $transactionManager = new InMemoryTransactionManager();
        $persister = new InMemoryPersister($repository, $registry, $transactionManager, $accessor);
        $relationshipReader = new InMemoryRelationshipReader($registry, $repository, $accessor);
        $existenceChecker = new InMemoryExistenceChecker($repository);
        $relationshipUpdater = new InMemoryRelationshipUpdater($registry, $repository);
        $linkageBuilder = new LinkageBuilder($registry, $relationshipReader, $pagination);
        $relationshipResponseConfig = new WriteRelationshipsResponseConfig('linkage');
        $relationshipValidator = new RelationshipDocumentValidator($registry, $existenceChecker, $errorMapper);

        $mediaTypePolicyProvider = new ConfigMediaTypePolicyProvider(
            [
                'default' => [
                    'request' => ['allowed' => [MediaType::JSON_API]],
                    'response' => [
                        'default' => MediaType::JSON_API,
                        'negotiable' => [],
                    ],
                ],
                'channels' => [],
            ],
            new ChannelScopeMatcher()
        );

        $this->mediaTypePolicyProvider = $mediaTypePolicyProvider;

        $atomicConfig = new AtomicConfig(true, '/api/operations', true, 100, 'auto', true, true, '/api');
        $mediaNegotiator = new MediaTypeNegotiator($atomicConfig, $mediaTypePolicyProvider);
        $atomicParser = new AtomicRequestParser($atomicConfig, $errorMapper);
        $atomicValidator = new AtomicValidator($atomicConfig, $registry, $errorMapper);
        $atomicTransaction = new AtomicTransaction($transactionManager);
        $addHandler = new AddHandler($persister, $changeSetFactory, $registry, $accessor);
        $updateHandler = new UpdateHandler($persister, $changeSetFactory, $registry, $accessor, $errorMapper);
        $removeHandler = new RemoveHandler($persister, $errorMapper);
        $relationshipOps = new RelationshipOps($relationshipUpdater, $registry, $errorMapper);
        $resultBuilder = new ResultBuilder($atomicConfig, $document);
        $dispatcher = new OperationDispatcher($atomicTransaction, $addHandler, $updateHandler, $removeHandler, $relationshipOps, $resultBuilder);
        $atomicController = new AtomicController($atomicParser, $atomicValidator, $dispatcher, $mediaNegotiator);

        // Create event dispatcher for testing
        $eventDispatcher = new EventDispatcher();

        // Create RelationshipResolver (in-memory version wrapped in mock)
        $inMemoryResolver = new InMemoryRelationshipResolver($repository, $registry, $accessor);
        $relationshipResolver = $this->createMock(RelationshipResolver::class);
        $relationshipResolver->method('applyRelationships')
            ->willReturnCallback(function (object $entity, array $relationshipsPayload, ResourceMetadata $resourceMetadata, bool $isCreate) use ($inMemoryResolver) {
                $inMemoryResolver->applyRelationships($entity, $relationshipsPayload, $resourceMetadata, $isCreate);
            });

        $this->registry = $registry;
        $this->repository = $repository;
        $this->parser = $parser;
        $this->document = $document;
        $this->collectionController = new CollectionController($registry, $repository, $parser, $document);
        $this->resourceController = new ResourceController($registry, $repository, $parser, $document, $errorMapper);
        $this->createController = new CreateResourceController($registry, $validator, $changeSetFactory, $persister, $transactionManager, $document, $linkGenerator, $writeConfig, $errorMapper, $violationMapper, $eventDispatcher, $relationshipResolver);
        $this->updateController = new UpdateResourceController($registry, $validator, $changeSetFactory, $persister, $transactionManager, $document, $errorMapper, $violationMapper, $eventDispatcher, $relationshipResolver);
        $this->deleteController = new DeleteResourceController($registry, $persister, $transactionManager, $eventDispatcher);
        $this->accessor = $accessor;
        $this->relationshipResolver = $relationshipResolver;
        $this->persister = $persister;
        $this->transactionManager = $transactionManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->relatedController = new RelatedController($registry, $relationshipReader, $parser, $document);
        $this->relationshipGetController = new RelationshipGetController($linkageBuilder);
        $this->relationshipWriteController = new RelationshipWriteController($relationshipValidator, $relationshipUpdater, $linkageBuilder, $relationshipResponseConfig, $errorMapper, $transactionManager, $eventDispatcher);
        $this->atomicController = $atomicController;

        $this->errorMapper = $errorMapper;
        $this->violationMapper = $violationMapper;
    }
}
