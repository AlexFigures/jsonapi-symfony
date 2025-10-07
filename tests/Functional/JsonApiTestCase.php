<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Functional;

use JsonApi\Symfony\Contract\Data\ResourcePersister;
use JsonApi\Symfony\Contract\Data\ResourceRepository;
use JsonApi\Symfony\Contract\Tx\TransactionManager;
use JsonApi\Symfony\Atomic\AtomicConfig;
use JsonApi\Symfony\Atomic\Execution\AtomicTransaction;
use JsonApi\Symfony\Atomic\Execution\Handlers\AddHandler;
use JsonApi\Symfony\Atomic\Execution\Handlers\RelationshipOps;
use JsonApi\Symfony\Atomic\Execution\Handlers\RemoveHandler;
use JsonApi\Symfony\Atomic\Execution\Handlers\UpdateHandler;
use JsonApi\Symfony\Atomic\Execution\OperationDispatcher;
use JsonApi\Symfony\Atomic\Parser\AtomicRequestParser;
use JsonApi\Symfony\Atomic\Result\ResultBuilder;
use JsonApi\Symfony\Atomic\Validation\AtomicValidator;
use JsonApi\Symfony\Bridge\Symfony\Controller\AtomicController;
use JsonApi\Symfony\Http\Controller\CollectionController;
use JsonApi\Symfony\Http\Controller\CreateResourceController;
use JsonApi\Symfony\Http\Controller\DeleteResourceController;
use JsonApi\Symfony\Http\Controller\RelatedController;
use JsonApi\Symfony\Http\Controller\RelationshipGetController;
use JsonApi\Symfony\Http\Controller\RelationshipWriteController;
use JsonApi\Symfony\Http\Controller\ResourceController;
use JsonApi\Symfony\Http\Controller\UpdateResourceController;
use JsonApi\Symfony\Http\Document\DocumentBuilder;
use JsonApi\Symfony\Http\Error\CorrelationIdProvider;
use JsonApi\Symfony\Http\Error\ErrorBuilder;
use JsonApi\Symfony\Http\Error\ErrorMapper;
use JsonApi\Symfony\Http\Error\JsonApiExceptionListener;
use JsonApi\Symfony\Http\Link\LinkGenerator;
use JsonApi\Symfony\Http\Relationship\LinkageBuilder;
use JsonApi\Symfony\Http\Relationship\WriteRelationshipsResponseConfig;
use JsonApi\Symfony\Http\Request\PaginationConfig;
use JsonApi\Symfony\Http\Request\QueryParser;
use JsonApi\Symfony\Http\Request\SortingWhitelist;
use JsonApi\Symfony\Http\Negotiation\MediaTypeNegotiator;
use JsonApi\Symfony\Http\Validation\ConstraintViolationMapper;
use JsonApi\Symfony\Http\Write\ChangeSetFactory;
use JsonApi\Symfony\Http\Write\InputDocumentValidator;
use JsonApi\Symfony\Http\Write\RelationshipDocumentValidator;
use JsonApi\Symfony\Http\Write\WriteConfig;
use JsonApi\Symfony\Resource\Registry\ResourceRegistry;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;
use JsonApi\Symfony\Tests\Fixtures\InMemory\InMemoryExistenceChecker;
use JsonApi\Symfony\Tests\Fixtures\InMemory\InMemoryPersister;
use JsonApi\Symfony\Tests\Fixtures\InMemory\InMemoryRelationshipReader;
use JsonApi\Symfony\Tests\Fixtures\InMemory\InMemoryRelationshipUpdater;
use JsonApi\Symfony\Tests\Fixtures\InMemory\InMemoryRepository;
use JsonApi\Symfony\Tests\Fixtures\InMemory\InMemoryTransactionManager;
use JsonApi\Symfony\Tests\Fixtures\Model\Article;
use JsonApi\Symfony\Tests\Fixtures\Model\Author;
use JsonApi\Symfony\Tests\Fixtures\Model\Tag;
use JsonApi\Symfony\Tests\Util\JsonApiResponseAsserts;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

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
    private ?ResourcePersister $persister = null;
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
            new class($correlationId) extends CorrelationIdProvider {
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

    protected function persister(): ResourcePersister
    {
        $this->boot();

        \assert($this->persister instanceof ResourcePersister);

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

        $filterParser = new \JsonApi\Symfony\Filter\Parser\FilterParser();
        $parser = new QueryParser($registry, $pagination, $sorting, $errorMapper, $filterParser);

        $routes = new RouteCollection();
        $routes->add('jsonapi.collection', new Route('/api/{type}'));
        $routes->add('jsonapi.resource', new Route('/api/{type}/{id}'));
        $routes->add('jsonapi.related', new Route('/api/{type}/{id}/{rel}'));
        $routes->add('jsonapi.relationship.get', new Route('/api/{type}/{id}/relationships/{rel}'));
        $routes->add('jsonapi.relationship.write', new Route('/api/{type}/{id}/relationships/{rel}'));

        $context = new RequestContext();
        $context->setScheme('http');
        $context->setHost('localhost');

        $urlGenerator = new UrlGenerator($routes, $context);
        $linkGenerator = new LinkGenerator($urlGenerator);
        $this->linkGenerator = $linkGenerator;
        $accessor = PropertyAccess::createPropertyAccessor();
        $document = new DocumentBuilder($registry, $accessor, $linkGenerator, 'when_included');
        $repository = new InMemoryRepository($registry, $accessor);
        $writeConfig = new WriteConfig(false, [
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

        $atomicConfig = new AtomicConfig(true, '/api/operations', true, 100, 'auto', true, true, '/api');
        $mediaNegotiator = new MediaTypeNegotiator($atomicConfig);
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

        $this->registry = $registry;
        $this->repository = $repository;
        $this->parser = $parser;
        $this->document = $document;
        $this->collectionController = new CollectionController($registry, $repository, $parser, $document);
        $this->resourceController = new ResourceController($registry, $repository, $parser, $document);
        $this->createController = new CreateResourceController($registry, $validator, $changeSetFactory, $persister, $transactionManager, $document, $linkGenerator, $writeConfig, $errorMapper, $violationMapper, $eventDispatcher);
        $this->updateController = new UpdateResourceController($registry, $validator, $changeSetFactory, $persister, $transactionManager, $document, $errorMapper, $violationMapper, $eventDispatcher);
        $this->deleteController = new DeleteResourceController($registry, $persister, $transactionManager, $eventDispatcher);
        $this->accessor = $accessor;
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
