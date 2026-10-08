<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Functional;

use AlexFigures\JsonApi\Atomic\AtomicConfig;
use AlexFigures\JsonApi\Atomic\Execution\AtomicTransaction;
use AlexFigures\JsonApi\Atomic\Execution\Handlers\AddHandler;
use AlexFigures\JsonApi\Atomic\Execution\Handlers\RelationshipOps;
use AlexFigures\JsonApi\Atomic\Execution\Handlers\RemoveHandler;
use AlexFigures\JsonApi\Atomic\Execution\Handlers\UpdateHandler;
use AlexFigures\JsonApi\Atomic\Execution\OperationDispatcher;
use AlexFigures\JsonApi\Atomic\Parser\AtomicRequestParser;
use AlexFigures\JsonApi\Atomic\Result\ResultBuilder;
use AlexFigures\JsonApi\Atomic\Validation\AtomicValidator;
use AlexFigures\JsonApi\Bridge\Doctrine\Flush\FlushManager;
use AlexFigures\JsonApi\Bridge\Symfony\Controller\AtomicController;
use AlexFigures\JsonApi\Bridge\Symfony\Negotiation\ChannelScopeMatcher;
use AlexFigures\JsonApi\Bridge\Symfony\Negotiation\ConfigMediaTypePolicyProvider;
use AlexFigures\JsonApi\Contract\Data\ResourceProcessor;
use AlexFigures\JsonApi\Contract\Data\ResourceRepository;
use AlexFigures\JsonApi\Contract\Tx\TransactionManager;
use AlexFigures\JsonApi\Http\Controller\CollectionController;
use AlexFigures\JsonApi\Http\Controller\CreateResourceController;
use AlexFigures\JsonApi\Http\Controller\DeleteResourceController;
use AlexFigures\JsonApi\Http\Controller\OptionsController;
use AlexFigures\JsonApi\Http\Controller\RelatedController;
use AlexFigures\JsonApi\Http\Controller\RelationshipGetController;
use AlexFigures\JsonApi\Http\Controller\RelationshipWriteController;
use AlexFigures\JsonApi\Http\Controller\ResourceController;
use AlexFigures\JsonApi\Http\Controller\Support\JsonApiResponseFactory;
use AlexFigures\JsonApi\Http\Controller\Support\OperationValidator;
use AlexFigures\JsonApi\Http\Controller\Support\RequestDecoder;
use AlexFigures\JsonApi\Http\Controller\UpdateResourceController;
use AlexFigures\JsonApi\Http\Document\DocumentBuilder;
use AlexFigures\JsonApi\Http\Error\CorrelationIdProvider;
use AlexFigures\JsonApi\Http\Error\ErrorBuilder;
use AlexFigures\JsonApi\Http\Error\ErrorMapper;
use AlexFigures\JsonApi\Http\Error\JsonApiExceptionListener;
use AlexFigures\JsonApi\Http\Link\LinkGenerator;
use AlexFigures\JsonApi\Http\Negotiation\MediaType;
use AlexFigures\JsonApi\Http\Negotiation\MediaTypeNegotiator;
use AlexFigures\JsonApi\Http\Negotiation\MediaTypePolicyProviderInterface;
use AlexFigures\JsonApi\Http\Relationship\LinkageBuilder;
use AlexFigures\JsonApi\Http\Relationship\WriteRelationshipsResponseConfig;
use AlexFigures\JsonApi\Http\Request\PaginationConfig;
use AlexFigures\JsonApi\Http\Request\QueryParser;
use AlexFigures\JsonApi\Http\Request\SortingWhitelist;
use AlexFigures\JsonApi\Http\Validation\ConstraintViolationMapper;
use AlexFigures\JsonApi\Http\Write\ChangeSetFactory;
use AlexFigures\JsonApi\Http\Write\InputDocumentValidator;
use AlexFigures\JsonApi\Http\Write\RelationshipDocumentValidator;
use AlexFigures\JsonApi\Http\Write\WriteConfig;
use AlexFigures\JsonApi\Resource\Metadata\ResourceMetadata;
use AlexFigures\JsonApi\Resource\Registry\ResourceRegistry;
use AlexFigures\JsonApi\Resource\Registry\ResourceRegistryInterface;
use AlexFigures\JsonApi\Resource\Relationship\RelationshipResolver;
use AlexFigures\JsonApi\Tests\Fixtures\Doctrine\TestManagerRegistry;
use AlexFigures\JsonApi\Tests\Fixtures\InMemory\InMemoryExistenceChecker;
use AlexFigures\JsonApi\Tests\Fixtures\InMemory\InMemoryPersister;
use AlexFigures\JsonApi\Tests\Fixtures\InMemory\InMemoryRelationshipReader;
use AlexFigures\JsonApi\Tests\Fixtures\InMemory\InMemoryRelationshipResolver;
use AlexFigures\JsonApi\Tests\Fixtures\InMemory\InMemoryRelationshipUpdater;
use AlexFigures\JsonApi\Tests\Fixtures\InMemory\InMemoryRepository;
use AlexFigures\JsonApi\Tests\Fixtures\InMemory\InMemoryTransactionManager;
use AlexFigures\JsonApi\Tests\Fixtures\Model\Article;
use AlexFigures\JsonApi\Tests\Fixtures\Model\Author;
use AlexFigures\JsonApi\Tests\Fixtures\Model\Tag;
use AlexFigures\JsonApi\Tests\Util\JsonApiResponseAsserts;
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
    private ?OptionsController $optionsController = null;
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

    protected function optionsController(): OptionsController
    {
        $this->boot();

        \assert($this->optionsController instanceof OptionsController);

        return $this->optionsController;
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
                public function __construct(private readonly string $id)
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
        $filtering = new \AlexFigures\JsonApi\Http\Request\FilteringWhitelist($registry, $errorMapper);

        $filterParser = new \AlexFigures\JsonApi\Filter\Parser\FilterParser();
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
        $managerRegistry = new TestManagerRegistry([]);
        $flushManager = new FlushManager($managerRegistry);
        $dispatcher = new OperationDispatcher($atomicTransaction, $addHandler, $updateHandler, $removeHandler, $relationshipOps, $resultBuilder, $flushManager);
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

        $operationValidator = new OperationValidator($errorMapper);
        $requestDecoder = new RequestDecoder($errorMapper);
        $responseFactory = new JsonApiResponseFactory();

        $this->registry = $registry;
        $this->repository = $repository;
        $this->parser = $parser;
        $this->document = $document;
        $this->collectionController = new CollectionController($registry, $operationValidator, $responseFactory, $repository, $parser, $document);
        $this->resourceController = new ResourceController($registry, $operationValidator, $responseFactory, $repository, $parser, $document, $errorMapper);
        $this->createController = new CreateResourceController($registry, $operationValidator, $requestDecoder, $responseFactory, $validator, $changeSetFactory, $persister, $transactionManager, $document, $linkGenerator, $writeConfig, $violationMapper, $eventDispatcher);
        $this->updateController = new UpdateResourceController($registry, $operationValidator, $requestDecoder, $responseFactory, $validator, $changeSetFactory, $persister, $transactionManager, $document, $violationMapper, $eventDispatcher);
        $this->deleteController = new DeleteResourceController($registry, $operationValidator, $persister, $transactionManager, $eventDispatcher);
        $this->accessor = $accessor;
        $this->relationshipResolver = $relationshipResolver;
        $this->persister = $persister;
        $this->transactionManager = $transactionManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->relatedController = new RelatedController($registry, $relationshipReader, $parser, $document, $errorMapper);
        $this->relationshipGetController = new RelationshipGetController($linkageBuilder, $registry, $errorMapper, $linkGenerator);
        $this->relationshipWriteController = new RelationshipWriteController($operationValidator, $requestDecoder, $relationshipValidator, $relationshipUpdater, $linkageBuilder, $relationshipResponseConfig, $transactionManager, $eventDispatcher, $registry);
        $this->optionsController = new OptionsController($registry);
        $this->atomicController = $atomicController;

        $this->errorMapper = $errorMapper;
        $this->violationMapper = $violationMapper;
    }
}
