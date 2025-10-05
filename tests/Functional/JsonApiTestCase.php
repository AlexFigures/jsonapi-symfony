<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Functional;

use JsonApi\Symfony\Contract\Data\ResourceRepository;
use JsonApi\Symfony\Contract\Data\ResourcePersister;
use JsonApi\Symfony\Contract\Tx\TransactionManager;
use JsonApi\Symfony\Http\Controller\CollectionController;
use JsonApi\Symfony\Http\Controller\CreateResourceController;
use JsonApi\Symfony\Http\Controller\DeleteResourceController;
use JsonApi\Symfony\Http\Controller\ResourceController;
use JsonApi\Symfony\Http\Controller\UpdateResourceController;
use JsonApi\Symfony\Http\Document\DocumentBuilder;
use JsonApi\Symfony\Http\Link\LinkGenerator;
use JsonApi\Symfony\Http\Request\PaginationConfig;
use JsonApi\Symfony\Http\Request\QueryParser;
use JsonApi\Symfony\Http\Request\SortingWhitelist;
use JsonApi\Symfony\Http\Write\ChangeSetFactory;
use JsonApi\Symfony\Http\Write\InputDocumentValidator;
use JsonApi\Symfony\Http\Write\WriteConfig;
use JsonApi\Symfony\Resource\Registry\ResourceRegistry;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;
use JsonApi\Symfony\Tests\Fixtures\InMemory\InMemoryRepository;
use JsonApi\Symfony\Tests\Fixtures\InMemory\InMemoryPersister;
use JsonApi\Symfony\Tests\Fixtures\InMemory\InMemoryTransactionManager;
use JsonApi\Symfony\Tests\Fixtures\Model\Article;
use JsonApi\Symfony\Tests\Fixtures\Model\Author;
use JsonApi\Symfony\Tests\Fixtures\Model\Tag;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

abstract class JsonApiTestCase extends TestCase
{
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

    protected function collectionController(): CollectionController
    {
        $this->boot();

        return $this->collectionController;
    }

    protected function resourceController(): ResourceController
    {
        $this->boot();

        return $this->resourceController;
    }

    protected function createController(): CreateResourceController
    {
        $this->boot();

        return $this->createController;
    }

    protected function updateController(): UpdateResourceController
    {
        $this->boot();

        return $this->updateController;
    }

    protected function deleteController(): DeleteResourceController
    {
        $this->boot();

        return $this->deleteController;
    }

    protected function registry(): ResourceRegistryInterface
    {
        $this->boot();

        return $this->registry;
    }

    protected function repository(): ResourceRepository
    {
        $this->boot();

        return $this->repository;
    }

    protected function parser(): QueryParser
    {
        $this->boot();

        return $this->parser;
    }

    protected function documentBuilder(): DocumentBuilder
    {
        $this->boot();

        return $this->document;
    }

    protected function propertyAccessor(): PropertyAccessorInterface
    {
        $this->boot();

        return $this->accessor;
    }

    protected function persister(): ResourcePersister
    {
        $this->boot();

        return $this->persister;
    }

    protected function transactionManager(): TransactionManager
    {
        $this->boot();

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

        $context = new RequestContext();
        $context->setScheme('http');
        $context->setHost('localhost');

        $urlGenerator = new UrlGenerator($routes, $context);
        $linkGenerator = new LinkGenerator($urlGenerator);
        $accessor = PropertyAccess::createPropertyAccessor();
        $document = new DocumentBuilder($registry, $accessor, $linkGenerator);
        $repository = new InMemoryRepository($registry, $accessor);
        $writeConfig = new WriteConfig(false, [
            'authors' => true,
        ]);
        $validator = new InputDocumentValidator($registry, $writeConfig);
        $changeSetFactory = new ChangeSetFactory($registry);
        $persister = new InMemoryPersister($repository, $registry, $accessor);
        $transactionManager = new InMemoryTransactionManager();

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
    }
}
