<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Functional;

use JsonApi\Symfony\Contract\Data\ResourceRepository;
use JsonApi\Symfony\Http\Controller\CollectionController;
use JsonApi\Symfony\Http\Controller\ResourceController;
use JsonApi\Symfony\Http\Document\DocumentBuilder;
use JsonApi\Symfony\Http\Link\LinkGenerator;
use JsonApi\Symfony\Http\Request\PaginationConfig;
use JsonApi\Symfony\Http\Request\QueryParser;
use JsonApi\Symfony\Http\Request\SortingWhitelist;
use JsonApi\Symfony\Resource\Registry\ResourceRegistry;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;
use JsonApi\Symfony\Tests\Fixtures\InMemory\InMemoryRepository;
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
        $accessor = PropertyAccess::createPropertyAccessor();
        $document = new DocumentBuilder($registry, $accessor, new LinkGenerator($urlGenerator));
        $repository = new InMemoryRepository($registry, $accessor);

        $this->registry = $registry;
        $this->repository = $repository;
        $this->parser = $parser;
        $this->document = $document;
        $this->collectionController = new CollectionController($registry, $repository, $parser, $document);
        $this->resourceController = new ResourceController($registry, $repository, $parser, $document);
        $this->accessor = $accessor;
    }
}
