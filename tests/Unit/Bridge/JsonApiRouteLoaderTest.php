<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Bridge;

use AlexFigures\Symfony\Bridge\Symfony\Routing\JsonApiRouteLoader;
use AlexFigures\Symfony\Bridge\Symfony\Routing\RouteNameGenerator;
use AlexFigures\Symfony\Resource\Metadata\RelationshipMetadata;
use AlexFigures\Symfony\Resource\Metadata\ResourceMetadata;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
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
        $this->assertSame('AlexFigures\Symfony\Http\Controller\CollectionController', $indexRoute->getDefault('_controller'));
        $this->assertSame('articles', $indexRoute->getDefault('type'));

        // Check collection POST route
        $this->assertTrue($routes->get('jsonapi.articles.create') !== null);
        $createRoute = $routes->get('jsonapi.articles.create');
        $this->assertSame('/api/articles', $createRoute->getPath());
        $this->assertSame(['POST'], $createRoute->getMethods());
        $this->assertSame('AlexFigures\Symfony\Http\Controller\CreateResourceController', $createRoute->getDefault('_controller'));
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
        $this->assertSame('AlexFigures\Symfony\Http\Controller\ResourceController', $showRoute->getDefault('_controller'));
        $this->assertSame('[^/]+', $showRoute->getRequirement('id'));

        // Check resource PATCH route
        $this->assertTrue($routes->get('jsonapi.articles.update') !== null);
        $updateRoute = $routes->get('jsonapi.articles.update');
        $this->assertSame('/api/articles/{id}', $updateRoute->getPath());
        $this->assertSame(['PATCH'], $updateRoute->getMethods());
        $this->assertSame('AlexFigures\Symfony\Http\Controller\UpdateResourceController', $updateRoute->getDefault('_controller'));
        $this->assertSame('[^/]+', $updateRoute->getRequirement('id'));

        // Check resource DELETE route
        $this->assertTrue($routes->get('jsonapi.articles.delete') !== null);
        $deleteRoute = $routes->get('jsonapi.articles.delete');
        $this->assertSame('/api/articles/{id}', $deleteRoute->getPath());
        $this->assertSame(['DELETE'], $deleteRoute->getMethods());
        $this->assertSame('AlexFigures\Symfony\Http\Controller\DeleteResourceController', $deleteRoute->getDefault('_controller'));
        $this->assertSame('[^/]+', $deleteRoute->getRequirement('id'));
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
        $this->assertSame('AlexFigures\Symfony\Http\Controller\RelationshipGetController', $showRelRoute->getDefault('_controller'));
        $this->assertSame('[^/]+', $showRelRoute->getRequirement('id'));

        // Check relationship PATCH route
        $this->assertTrue($routes->get('jsonapi.articles.relationships.author.update') !== null);
        $updateRelRoute = $routes->get('jsonapi.articles.relationships.author.update');
        $this->assertSame('/api/articles/{id}/relationships/author', $updateRelRoute->getPath());
        $this->assertSame(['PATCH'], $updateRelRoute->getMethods());
        $this->assertSame('AlexFigures\Symfony\Http\Controller\RelationshipWriteController', $updateRelRoute->getDefault('_controller'));
        $this->assertSame('[^/]+', $updateRelRoute->getRequirement('id'));

        // Check to-many relationship POST route
        $this->assertTrue($routes->get('jsonapi.articles.relationships.tags.add') !== null);
        $addRelRoute = $routes->get('jsonapi.articles.relationships.tags.add');
        $this->assertSame('/api/articles/{id}/relationships/tags', $addRelRoute->getPath());
        $this->assertSame(['POST'], $addRelRoute->getMethods());
        $this->assertSame('AlexFigures\Symfony\Http\Controller\RelationshipWriteController', $addRelRoute->getDefault('_controller'));
        $this->assertSame('[^/]+', $addRelRoute->getRequirement('id'));

        // Check to-many relationship DELETE route
        $this->assertTrue($routes->get('jsonapi.articles.relationships.tags.remove') !== null);
        $removeRelRoute = $routes->get('jsonapi.articles.relationships.tags.remove');
        $this->assertSame('/api/articles/{id}/relationships/tags', $removeRelRoute->getPath());
        $this->assertSame(['DELETE'], $removeRelRoute->getMethods());
        $this->assertSame('AlexFigures\Symfony\Http\Controller\RelationshipWriteController', $removeRelRoute->getDefault('_controller'));
        $this->assertSame('[^/]+', $removeRelRoute->getRequirement('id'));

        // Check related resource route
        $this->assertTrue($routes->get('jsonapi.articles.related.author') !== null);
        $relatedRoute = $routes->get('jsonapi.articles.related.author');
        $this->assertSame('/api/articles/{id}/author', $relatedRoute->getPath());
        $this->assertSame(['GET'], $relatedRoute->getMethods());
        $this->assertSame('AlexFigures\Symfony\Http\Controller\RelatedController', $relatedRoute->getDefault('_controller'));
        $this->assertSame('[^/]+', $relatedRoute->getRequirement('id'));
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

    public function testAddsDocumentationRoutesWhenEnabled(): void
    {
        $registry = $this->createMock(ResourceRegistryInterface::class);
        $registry->method('all')->willReturn([]);

        $openApiConfig = [
            'enabled' => true,
            'route' => '/_jsonapi/openapi.json',
        ];

        $uiConfig = [
            'enabled' => true,
            'route' => '/_jsonapi/docs',
        ];

        $loader = new JsonApiRouteLoader($registry, '/api', true, $openApiConfig, $uiConfig);
        $routes = $loader->load('.', 'jsonapi');

        $openApiRoute = $routes->get('jsonapi.docs.openapi');
        self::assertNotNull($openApiRoute);
        self::assertSame('/_jsonapi/openapi.json', $openApiRoute->getPath());
        self::assertSame('AlexFigures\Symfony\Http\Controller\OpenApiController', $openApiRoute->getDefault('_controller'));
        self::assertSame(['GET'], $openApiRoute->getMethods());

        $uiRoute = $routes->get('jsonapi.docs.ui');
        self::assertNotNull($uiRoute);
        self::assertSame('/_jsonapi/docs', $uiRoute->getPath());
        self::assertSame('AlexFigures\Symfony\Http\Controller\SwaggerUiController', $uiRoute->getDefault('_controller'));
        self::assertSame(['GET'], $uiRoute->getMethods());
    }

    public function testDocumentationRoutesRespectCustomPaths(): void
    {
        $registry = $this->createMock(ResourceRegistryInterface::class);
        $registry->method('all')->willReturn([]);

        $openApiConfig = [
            'enabled' => true,
            'route' => '/doc/openapi.json',
        ];

        $uiConfig = [
            'enabled' => true,
            'route' => '/doc/browser',
        ];

        $loader = new JsonApiRouteLoader($registry, '/api', true, $openApiConfig, $uiConfig);
        $routes = $loader->load('.', 'jsonapi');

        $openApiRoute = $routes->get('jsonapi.docs.openapi');
        self::assertNotNull($openApiRoute);
        self::assertSame('/doc/openapi.json', $openApiRoute->getPath());

        $uiRoute = $routes->get('jsonapi.docs.ui');
        self::assertNotNull($uiRoute);
        self::assertSame('/doc/browser', $uiRoute->getPath());
    }

    public function testDocumentationRoutesCanBeDisabled(): void
    {
        $registry = $this->createMock(ResourceRegistryInterface::class);
        $registry->method('all')->willReturn([]);

        $openApiConfig = [
            'enabled' => false,
            'route' => '/_jsonapi/openapi.json',
        ];

        $uiConfig = [
            'enabled' => false,
            'route' => '/_jsonapi/docs',
        ];

        $loader = new JsonApiRouteLoader($registry, '/api', true, $openApiConfig, $uiConfig);
        $routes = $loader->load('.', 'jsonapi');

        self::assertNull($routes->get('jsonapi.docs.openapi'));
        self::assertNull($routes->get('jsonapi.docs.ui'));
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

    public function testKebabCaseNamingConvention(): void
    {
        $registry = $this->createMock(ResourceRegistryInterface::class);
        $registry->method('all')->willReturn([
            new ResourceMetadata(
                type: 'blog_posts',
                class: 'App\Entity\BlogPost',
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

        $routeNameGenerator = new RouteNameGenerator(RouteNameGenerator::KEBAB_CASE);
        $loader = new JsonApiRouteLoader($registry, '/api', true, [], [], $routeNameGenerator);
        $routes = $loader->load('.', 'jsonapi');

        // Check that route names use kebab-case
        $this->assertNotNull($routes->get('jsonapi.blog-posts.index'));
        $this->assertNotNull($routes->get('jsonapi.blog-posts.create'));
        $this->assertNotNull($routes->get('jsonapi.blog-posts.show'));
        $this->assertNotNull($routes->get('jsonapi.blog-posts.update'));
        $this->assertNotNull($routes->get('jsonapi.blog-posts.delete'));

        // Check relationship routes
        $this->assertNotNull($routes->get('jsonapi.blog-posts.relationships.author.show'));
        $this->assertNotNull($routes->get('jsonapi.blog-posts.relationships.author.update'));
        $this->assertNotNull($routes->get('jsonapi.blog-posts.related.author'));

        // Verify old snake_case routes don't exist
        $this->assertNull($routes->get('jsonapi.blog_posts.index'));
        $this->assertNull($routes->get('jsonapi.blog_posts.relationships.author.show'));
    }

    public function testCustomRoutes(): void
    {
        $registry = $this->createMock(ResourceRegistryInterface::class);
        $registry->method('all')->willReturn([]);

        $customRouteRegistry = $this->createMock(\AlexFigures\Symfony\Resource\Registry\CustomRouteRegistryInterface::class);
        $customRouteRegistry->method('all')->willReturn([
            new \AlexFigures\Symfony\Resource\Metadata\CustomRouteMetadata(
                name: 'articles.publish',
                path: '/api/articles/{id}/publish',
                methods: ['POST'],
                handler: null,
                controller: 'App\Controller\PublishController',
                resourceType: 'articles',
                defaults: ['_format' => 'json'],
                requirements: ['id' => '\d+'],
                description: 'Publish an article',
                priority: 0,
            ),
            new \AlexFigures\Symfony\Resource\Metadata\CustomRouteMetadata(
                name: 'articles.search',
                path: '/api/articles/search',
                methods: ['GET'],
                handler: null,
                controller: 'App\Controller\SearchController',
                resourceType: 'articles',
                defaults: [],
                requirements: [],
                description: 'Search articles',
                priority: 10,
            ),
        ]);

        $loader = new JsonApiRouteLoader($registry, '/api', true, [], [], null, $customRouteRegistry);
        $routes = $loader->load('.', 'jsonapi');

        // Check custom routes are added
        $publishRoute = $routes->get('articles.publish');
        $this->assertNotNull($publishRoute);
        $this->assertSame('/api/articles/{id}/publish', $publishRoute->getPath());
        $this->assertSame(['POST'], $publishRoute->getMethods());
        $this->assertSame('App\Controller\PublishController', $publishRoute->getDefault('_controller'));
        $this->assertSame('articles', $publishRoute->getDefault('type'));
        $this->assertSame('json', $publishRoute->getDefault('_format'));
        $this->assertSame('\d+', $publishRoute->getRequirement('id'));

        $searchRoute = $routes->get('articles.search');
        $this->assertNotNull($searchRoute);
        $this->assertSame('/api/articles/search', $searchRoute->getPath());
        $this->assertSame(['GET'], $searchRoute->getMethods());
        $this->assertSame('App\Controller\SearchController', $searchRoute->getDefault('_controller'));
        $this->assertSame('articles', $searchRoute->getDefault('type'));
    }

    public function testCustomRoutesPreserveComplexNames(): void
    {
        $registry = $this->createMock(ResourceRegistryInterface::class);
        $registry->method('all')->willReturn([]);

        $routeNameGenerator = new \AlexFigures\Symfony\Bridge\Symfony\Routing\RouteNameGenerator('kebab-case');

        $customRouteRegistry = $this->createMock(\AlexFigures\Symfony\Resource\Registry\CustomRouteRegistryInterface::class);
        $customRouteRegistry->method('all')->willReturn([
            // This should be transformed (canonical 3-part pattern)
            new \AlexFigures\Symfony\Resource\Metadata\CustomRouteMetadata(
                name: 'jsonapi.products.publish',
                path: '/api/products/{id}/publish',
                methods: ['POST'],
                handler: null,
                controller: 'App\Controller\PublishController',
                resourceType: 'products',
                defaults: [],
                requirements: [],
                description: null,
                priority: 0,
            ),
            // This should NOT be transformed (4-part pattern)
            new \AlexFigures\Symfony\Resource\Metadata\CustomRouteMetadata(
                name: 'jsonapi.products.actions.archive',
                path: '/api/products/{id}/archive',
                methods: ['POST'],
                handler: null,
                controller: 'App\Controller\ArchiveController',
                resourceType: 'products',
                defaults: [],
                requirements: [],
                description: null,
                priority: 0,
            ),
            // This should NOT be transformed (doesn't start with jsonapi.)
            new \AlexFigures\Symfony\Resource\Metadata\CustomRouteMetadata(
                name: 'custom.products.special',
                path: '/api/products/special',
                methods: ['GET'],
                handler: null,
                controller: 'App\Controller\SpecialController',
                resourceType: 'products',
                defaults: [],
                requirements: [],
                description: null,
                priority: 0,
            ),
        ]);

        $loader = new JsonApiRouteLoader($registry, '/api', true, [], [], $routeNameGenerator, $customRouteRegistry);
        $routes = $loader->load('.', 'jsonapi');

        // Check that canonical 3-part name was transformed (kebab-case)
        $publishRoute = $routes->get('jsonapi.products.publish');
        $this->assertNotNull($publishRoute);

        // Check that 4-part name was preserved exactly
        $archiveRoute = $routes->get('jsonapi.products.actions.archive');
        $this->assertNotNull($archiveRoute);
        $this->assertSame('/api/products/{id}/archive', $archiveRoute->getPath());

        // Check that non-jsonapi name was preserved exactly
        $specialRoute = $routes->get('custom.products.special');
        $this->assertNotNull($specialRoute);
        $this->assertSame('/api/products/special', $specialRoute->getPath());
    }

    public function testCustomRoutesPriorityOrdering(): void
    {
        $registry = $this->createMock(ResourceRegistryInterface::class);
        $registry->method('all')->willReturn([
            new \AlexFigures\Symfony\Resource\Metadata\ResourceMetadata(
                type: 'articles',
                class: 'App\Entity\Article',
                attributes: [],
                relationships: [],
            ),
        ]);

        $customRouteRegistry = $this->createMock(\AlexFigures\Symfony\Resource\Registry\CustomRouteRegistryInterface::class);
        $customRouteRegistry->method('all')->willReturn([
            // High priority route - should be added BEFORE auto-generated routes
            new \AlexFigures\Symfony\Resource\Metadata\CustomRouteMetadata(
                name: 'articles.search',
                path: '/api/articles/search',
                methods: ['GET'],
                handler: null,
                controller: 'App\Controller\SearchController',
                resourceType: 'articles',
                defaults: [],
                requirements: [],
                description: null,
                priority: 10, // High priority
            ),
            // Low priority route - should be added AFTER auto-generated routes
            new \AlexFigures\Symfony\Resource\Metadata\CustomRouteMetadata(
                name: 'articles.archive',
                path: '/api/articles/archive',
                methods: ['POST'],
                handler: null,
                controller: 'App\Controller\ArchiveController',
                resourceType: 'articles',
                defaults: [],
                requirements: [],
                description: null,
                priority: -5, // Low priority
            ),
        ]);

        $loader = new JsonApiRouteLoader($registry, '/api', true, [], [], null, $customRouteRegistry);
        $routes = $loader->load('.', 'jsonapi');

        // Get all route names in order
        $routeNames = array_keys($routes->all());

        // High priority custom route should come before auto-generated routes
        $searchIndex = array_search('articles.search', $routeNames);
        $showIndex = array_search('jsonapi.articles.show', $routeNames);
        $this->assertNotFalse($searchIndex, 'Search route should exist');
        $this->assertNotFalse($showIndex, 'Show route should exist');
        $this->assertLessThan($showIndex, $searchIndex, 'High priority custom route should come before auto-generated routes');

        // Low priority custom route should come after auto-generated routes
        $archiveIndex = array_search('articles.archive', $routeNames);
        $this->assertNotFalse($archiveIndex, 'Archive route should exist');
        $this->assertGreaterThan($showIndex, $archiveIndex, 'Low priority custom route should come after auto-generated routes');

        // Verify the routes exist and have correct paths
        $searchRoute = $routes->get('articles.search');
        $this->assertNotNull($searchRoute);
        $this->assertSame('/api/articles/search', $searchRoute->getPath());

        $archiveRoute = $routes->get('articles.archive');
        $this->assertNotNull($archiveRoute);
        $this->assertSame('/api/articles/archive', $archiveRoute->getPath());
    }

    /**
     * Test that resource types with multiple underscores generate correct routes.
     *
     * This test verifies that resource types like 'category_synonyms' work correctly
     * with both snake_case and kebab-case naming conventions.
     */
    public function testResourceTypeWithMultipleUnderscores(): void
    {
        $registry = $this->createMock(ResourceRegistryInterface::class);
        $registry->method('all')->willReturn([
            new ResourceMetadata(
                type: 'category_synonyms',
                class: 'App\Entity\CategorySynonym',
                attributes: [],
                relationships: [
                    new RelationshipMetadata(
                        name: 'category',
                        toMany: false,
                        targetType: 'categories',
                        propertyPath: 'category',
                    ),
                ],
            ),
        ]);

        // Test with snake_case naming convention (default)
        $snakeLoader = new JsonApiRouteLoader($registry, '/api', true);
        $snakeRoutes = $snakeLoader->load('.', 'jsonapi');

        // Verify route names use snake_case
        $this->assertNotNull($snakeRoutes->get('jsonapi.category_synonyms.index'));
        $this->assertNotNull($snakeRoutes->get('jsonapi.category_synonyms.show'));
        $this->assertNotNull($snakeRoutes->get('jsonapi.category_synonyms.create'));
        $this->assertNotNull($snakeRoutes->get('jsonapi.category_synonyms.update'));
        $this->assertNotNull($snakeRoutes->get('jsonapi.category_synonyms.delete'));

        // Verify relationship routes
        $this->assertNotNull($snakeRoutes->get('jsonapi.category_synonyms.relationships.category.show'));
        $this->assertNotNull($snakeRoutes->get('jsonapi.category_synonyms.related.category'));

        // Verify URL paths use resource type as-is (with underscores)
        $indexRoute = $snakeRoutes->get('jsonapi.category_synonyms.index');
        $this->assertSame('/api/category_synonyms', $indexRoute->getPath());

        $showRoute = $snakeRoutes->get('jsonapi.category_synonyms.show');
        $this->assertSame('/api/category_synonyms/{id}', $showRoute->getPath());

        // Test with kebab-case naming convention
        $routeNameGenerator = new RouteNameGenerator(RouteNameGenerator::KEBAB_CASE);
        $kebabLoader = new JsonApiRouteLoader($registry, '/api', true, [], [], $routeNameGenerator);
        $kebabRoutes = $kebabLoader->load('.', 'jsonapi');

        // Verify route names use kebab-case
        $this->assertNotNull($kebabRoutes->get('jsonapi.category-synonyms.index'));
        $this->assertNotNull($kebabRoutes->get('jsonapi.category-synonyms.show'));
        $this->assertNotNull($kebabRoutes->get('jsonapi.category-synonyms.create'));
        $this->assertNotNull($kebabRoutes->get('jsonapi.category-synonyms.update'));
        $this->assertNotNull($kebabRoutes->get('jsonapi.category-synonyms.delete'));

        // Verify relationship routes use kebab-case
        $this->assertNotNull($kebabRoutes->get('jsonapi.category-synonyms.relationships.category.show'));
        $this->assertNotNull($kebabRoutes->get('jsonapi.category-synonyms.related.category'));

        // Verify URL paths still use resource type as-is (with underscores, NOT hyphens)
        $kebabIndexRoute = $kebabRoutes->get('jsonapi.category-synonyms.index');
        $this->assertSame('/api/category_synonyms', $kebabIndexRoute->getPath());

        $kebabShowRoute = $kebabRoutes->get('jsonapi.category-synonyms.show');
        $this->assertSame('/api/category_synonyms/{id}', $kebabShowRoute->getPath());

        // Verify old snake_case route names don't exist when using kebab-case
        $this->assertNull($kebabRoutes->get('jsonapi.category_synonyms.index'));
        $this->assertNull($kebabRoutes->get('jsonapi.category_synonyms.relationships.category.show'));
    }
}
