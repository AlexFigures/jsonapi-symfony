<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Unit\Bridge;

use JsonApi\Symfony\Bridge\Symfony\Routing\JsonApiRouteLoader;
use JsonApi\Symfony\Resource\Metadata\RelationshipMetadata;
use JsonApi\Symfony\Resource\Metadata\ResourceMetadata;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonApiRouteLoader::class)]
final class JsonApiRouteLoaderTest extends TestCase
{
    public function testGeneratesCollectionRoutes(): void
    {
        $registry = $this->createMock(ResourceRegistryInterface::class);
        $registry->method('all')->willReturn([
            new ResourceMetadata(
                type: 'articles',
                class: 'App\Entity\Article',
                attributes: [],
                relationships: [],
            ),
        ]);

        $loader = new JsonApiRouteLoader($registry, '/api', true);
        $routes = $loader->load('.', 'jsonapi');

        // Check collection GET route
        $this->assertTrue($routes->get('jsonapi.articles.index') !== null);
        $indexRoute = $routes->get('jsonapi.articles.index');
        $this->assertSame('/api/articles', $indexRoute->getPath());
        $this->assertSame(['GET'], $indexRoute->getMethods());
        $this->assertSame('JsonApi\Symfony\Http\Controller\CollectionController', $indexRoute->getDefault('_controller'));
        $this->assertSame('articles', $indexRoute->getDefault('type'));

        // Check collection POST route
        $this->assertTrue($routes->get('jsonapi.articles.create') !== null);
        $createRoute = $routes->get('jsonapi.articles.create');
        $this->assertSame('/api/articles', $createRoute->getPath());
        $this->assertSame(['POST'], $createRoute->getMethods());
        $this->assertSame('JsonApi\Symfony\Http\Controller\CreateResourceController', $createRoute->getDefault('_controller'));
    }

    public function testGeneratesResourceRoutes(): void
    {
        $registry = $this->createMock(ResourceRegistryInterface::class);
        $registry->method('all')->willReturn([
            new ResourceMetadata(
                type: 'articles',
                class: 'App\Entity\Article',
                attributes: [],
                relationships: [],
            ),
        ]);

        $loader = new JsonApiRouteLoader($registry, '/api', true);
        $routes = $loader->load('.', 'jsonapi');

        // Check resource GET route
        $this->assertTrue($routes->get('jsonapi.articles.show') !== null);
        $showRoute = $routes->get('jsonapi.articles.show');
        $this->assertSame('/api/articles/{id}', $showRoute->getPath());
        $this->assertSame(['GET'], $showRoute->getMethods());
        $this->assertSame('JsonApi\Symfony\Http\Controller\ResourceController', $showRoute->getDefault('_controller'));

        // Check resource PATCH route
        $this->assertTrue($routes->get('jsonapi.articles.update') !== null);
        $updateRoute = $routes->get('jsonapi.articles.update');
        $this->assertSame('/api/articles/{id}', $updateRoute->getPath());
        $this->assertSame(['PATCH'], $updateRoute->getMethods());
        $this->assertSame('JsonApi\Symfony\Http\Controller\UpdateResourceController', $updateRoute->getDefault('_controller'));

        // Check resource DELETE route
        $this->assertTrue($routes->get('jsonapi.articles.delete') !== null);
        $deleteRoute = $routes->get('jsonapi.articles.delete');
        $this->assertSame('/api/articles/{id}', $deleteRoute->getPath());
        $this->assertSame(['DELETE'], $deleteRoute->getMethods());
        $this->assertSame('JsonApi\Symfony\Http\Controller\DeleteResourceController', $deleteRoute->getDefault('_controller'));
    }

    public function testGeneratesRelationshipRoutes(): void
    {
        $registry = $this->createMock(ResourceRegistryInterface::class);
        $registry->method('all')->willReturn([
            new ResourceMetadata(
                type: 'articles',
                class: 'App\Entity\Article',
                attributes: [],
                relationships: [
                    new RelationshipMetadata(
                        name: 'author',
                        toMany: false,
                        targetType: 'authors',
                        propertyPath: 'author',
                    ),
                    new RelationshipMetadata(
                        name: 'tags',
                        toMany: true,
                        targetType: 'tags',
                        propertyPath: 'tags',
                    ),
                ],
            ),
        ]);

        $loader = new JsonApiRouteLoader($registry, '/api', true);
        $routes = $loader->load('.', 'jsonapi');

        // Check relationship GET route
        $this->assertTrue($routes->get('jsonapi.articles.relationships.author.show') !== null);
        $showRelRoute = $routes->get('jsonapi.articles.relationships.author.show');
        $this->assertSame('/api/articles/{id}/relationships/author', $showRelRoute->getPath());
        $this->assertSame(['GET'], $showRelRoute->getMethods());
        $this->assertSame('JsonApi\Symfony\Http\Controller\RelationshipGetController', $showRelRoute->getDefault('_controller'));

        // Check relationship PATCH route
        $this->assertTrue($routes->get('jsonapi.articles.relationships.author.update') !== null);
        $updateRelRoute = $routes->get('jsonapi.articles.relationships.author.update');
        $this->assertSame('/api/articles/{id}/relationships/author', $updateRelRoute->getPath());
        $this->assertSame(['PATCH'], $updateRelRoute->getMethods());
        $this->assertSame('JsonApi\Symfony\Http\Controller\RelationshipWriteController', $updateRelRoute->getDefault('_controller'));

        // Check to-many relationship POST route
        $this->assertTrue($routes->get('jsonapi.articles.relationships.tags.add') !== null);
        $addRelRoute = $routes->get('jsonapi.articles.relationships.tags.add');
        $this->assertSame('/api/articles/{id}/relationships/tags', $addRelRoute->getPath());
        $this->assertSame(['POST'], $addRelRoute->getMethods());
        $this->assertSame('JsonApi\Symfony\Http\Controller\RelationshipWriteController', $addRelRoute->getDefault('_controller'));

        // Check to-many relationship DELETE route
        $this->assertTrue($routes->get('jsonapi.articles.relationships.tags.remove') !== null);
        $removeRelRoute = $routes->get('jsonapi.articles.relationships.tags.remove');
        $this->assertSame('/api/articles/{id}/relationships/tags', $removeRelRoute->getPath());
        $this->assertSame(['DELETE'], $removeRelRoute->getMethods());
        $this->assertSame('JsonApi\Symfony\Http\Controller\RelationshipWriteController', $removeRelRoute->getDefault('_controller'));

        // Check related resource route
        $this->assertTrue($routes->get('jsonapi.articles.related.author') !== null);
        $relatedRoute = $routes->get('jsonapi.articles.related.author');
        $this->assertSame('/api/articles/{id}/author', $relatedRoute->getPath());
        $this->assertSame(['GET'], $relatedRoute->getMethods());
        $this->assertSame('JsonApi\Symfony\Http\Controller\RelatedController', $relatedRoute->getDefault('_controller'));
    }

    public function testCustomRoutePrefix(): void
    {
        $registry = $this->createMock(ResourceRegistryInterface::class);
        $registry->method('all')->willReturn([
            new ResourceMetadata(
                type: 'articles',
                class: 'App\Entity\Article',
                attributes: [],
                relationships: [],
            ),
        ]);

        $loader = new JsonApiRouteLoader($registry, '/custom/prefix', true);
        $routes = $loader->load('.', 'jsonapi');

        $indexRoute = $routes->get('jsonapi.articles.index');
        $this->assertSame('/custom/prefix/articles', $indexRoute->getPath());
    }

    public function testDisableRelationshipRoutes(): void
    {
        $registry = $this->createMock(ResourceRegistryInterface::class);
        $registry->method('all')->willReturn([
            new ResourceMetadata(
                type: 'articles',
                class: 'App\Entity\Article',
                attributes: [],
                relationships: [
                    new RelationshipMetadata(
                        name: 'author',
                        toMany: false,
                        targetType: 'authors',
                        propertyPath: 'author',
                    ),
                ],
            ),
        ]);

        $loader = new JsonApiRouteLoader($registry, '/api', false);
        $routes = $loader->load('.', 'jsonapi');

        // Relationship routes should not be generated
        $this->assertNull($routes->get('jsonapi.articles.relationships.author.show'));
        $this->assertNull($routes->get('jsonapi.articles.related.author'));

        // But resource routes should still exist
        $this->assertNotNull($routes->get('jsonapi.articles.index'));
        $this->assertNotNull($routes->get('jsonapi.articles.show'));
    }

    public function testSupportsJsonApiType(): void
    {
        $registry = $this->createMock(ResourceRegistryInterface::class);
        $loader = new JsonApiRouteLoader($registry);

        $this->assertTrue($loader->supports('.', 'jsonapi'));
        $this->assertFalse($loader->supports('.', 'yaml'));
        $this->assertFalse($loader->supports('.', 'xml'));
    }

    public function testThrowsWhenLoadedTwice(): void
    {
        $registry = $this->createMock(ResourceRegistryInterface::class);
        $registry->method('all')->willReturn([]);

        $loader = new JsonApiRouteLoader($registry);
        $loader->load('.', 'jsonapi');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Do not add the "jsonapi" loader twice');
        $loader->load('.', 'jsonapi');
    }
}

