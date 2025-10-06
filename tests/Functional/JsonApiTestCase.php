<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Functional;

use JsonApi\Symfony\Contract\Data\ResourcePersister;
use JsonApi\Symfony\Contract\Data\ResourceRepository;
use JsonApi\Symfony\Contract\Tx\TransactionManager;
use JsonApi\Symfony\Http\Controller\CollectionController;
use JsonApi\Symfony\Http\Controller\CreateResourceController;
use JsonApi\Symfony\Http\Controller\DeleteResourceController;
use JsonApi\Symfony\Http\Controller\RelatedController;
use JsonApi\Symfony\Http\Controller\RelationshipGetController;
use JsonApi\Symfony\Http\Controller\RelationshipWriteController;
use JsonApi\Symfony\Http\Controller\ResourceController;
use JsonApi\Symfony\Http\Controller\UpdateResourceController;
use JsonApi\Symfony\Http\Document\DocumentBuilder;
use JsonApi\Symfony\Http\Link\LinkGenerator;
use JsonApi\Symfony\Http\Relationship\LinkageBuilder;
use JsonApi\Symfony\Http\Relationship\WriteRelationshipsResponseConfig;
use JsonApi\Symfony\Http\Request\PaginationConfig;
use JsonApi\Symfony\Http\Request\QueryParser;
use JsonApi\Symfony\Http\Request\SortingWhitelist;
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
    private ?ResourcePersister $persister = null;
    private ?TransactionManager $transactionManager = null;
    private ?RelatedController $relatedController = null;
    private ?RelationshipGetController $relationshipGetController = null;
    private ?RelationshipWriteController $relationshipWriteController = null;

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
        $sorting = new SortingWhitelist([
            'articles' => ['title', 'createdAt'],
            'authors' => ['name'],
            'tags' => ['name'],
        ]);

        $parser = new QueryParser($registry, $pagination, $sorting);

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
        $accessor = PropertyAccess::createPropertyAccessor();
        $document = new DocumentBuilder($registry, $accessor, $linkGenerator, 'when_included');
        $repository = new InMemoryRepository($registry, $accessor);
        $writeConfig = new WriteConfig(false, [
            'authors' => true,
        ]);
        $validator = new InputDocumentValidator($registry, $writeConfig);
        $changeSetFactory = new ChangeSetFactory($registry);
        $persister = new InMemoryPersister($repository, $registry, $accessor);
        $transactionManager = new InMemoryTransactionManager();
        $relationshipReader = new InMemoryRelationshipReader($registry, $repository, $accessor);
        $existenceChecker = new InMemoryExistenceChecker($repository);
        $relationshipUpdater = new InMemoryRelationshipUpdater($registry, $repository);
        $linkageBuilder = new LinkageBuilder($registry, $relationshipReader, $pagination);
        $relationshipResponseConfig = new WriteRelationshipsResponseConfig('linkage');
        $relationshipValidator = new RelationshipDocumentValidator($registry, $existenceChecker);

        $this->registry = $registry;
        $this->repository = $repository;
        $this->parser = $parser;
        $this->document = $document;
        $this->collectionController = new CollectionController($registry, $repository, $parser, $document);
        $this->resourceController = new ResourceController($registry, $repository, $parser, $document);
        $this->createController = new CreateResourceController($registry, $validator, $changeSetFactory, $persister, $transactionManager, $document, $linkGenerator, $writeConfig);
        $this->updateController = new UpdateResourceController($registry, $validator, $changeSetFactory, $persister, $transactionManager, $document);
        $this->deleteController = new DeleteResourceController($registry, $persister, $transactionManager);
        $this->accessor = $accessor;
        $this->persister = $persister;
        $this->transactionManager = $transactionManager;
        $this->relatedController = new RelatedController($registry, $relationshipReader, $parser, $document);
        $this->relationshipGetController = new RelationshipGetController($linkageBuilder);
        $this->relationshipWriteController = new RelationshipWriteController($relationshipValidator, $relationshipUpdater, $linkageBuilder, $relationshipResponseConfig);
    }
}
