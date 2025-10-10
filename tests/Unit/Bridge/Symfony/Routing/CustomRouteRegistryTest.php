<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Unit\Bridge\Symfony\Routing;

use JsonApi\Symfony\Resource\Metadata\CustomRouteMetadata;
use JsonApi\Symfony\Resource\Registry\CustomRouteRegistry;
use PHPUnit\Framework\TestCase;

final class CustomRouteRegistryTest extends TestCase
{
    public function testAddAndRetrieveRoutes(): void
    {
        $registry = new CustomRouteRegistry();

        $route1 = new CustomRouteMetadata(
            name: 'articles.publish',
            path: '/articles/{id}/publish',
            methods: ['POST'],
            handler: null,
            controller: 'App\Controller\PublishController',
            resourceType: 'articles',
            defaults: [],
            requirements: [],
            description: 'Publish an article',
            priority: 0,
        );

        $route2 = new CustomRouteMetadata(
            name: 'articles.search',
            path: '/articles/search',
            methods: ['GET'],
            handler: null,
            controller: 'App\Controller\SearchController',
            resourceType: 'articles',
            defaults: [],
            requirements: [],
            description: 'Search articles',
            priority: 10,
        );

        $registry->addRoute($route1);
        $registry->addRoute($route2);

        $allRoutes = $registry->all();
        self::assertCount(2, $allRoutes);

        // Should be sorted by priority (higher first)
        self::assertSame('articles.search', $allRoutes[0]->name);
        self::assertSame('articles.publish', $allRoutes[1]->name);
    }

    public function testGetByResourceType(): void
    {
        $registry = new CustomRouteRegistry();

        $articleRoute = new CustomRouteMetadata(
            name: 'articles.publish',
            path: '/articles/{id}/publish',
            methods: ['POST'],
            handler: null,
            controller: 'App\Controller\PublishController',
            resourceType: 'articles',
            defaults: [],
            requirements: [],
            description: null,
            priority: 0,
        );

        $userRoute = new CustomRouteMetadata(
            name: 'users.activate',
            path: '/users/{id}/activate',
            methods: ['POST'],
            handler: null,
            controller: 'App\Controller\ActivateController',
            resourceType: 'users',
            defaults: [],
            requirements: [],
            description: null,
            priority: 0,
        );

        $registry->addRoute($articleRoute);
        $registry->addRoute($userRoute);

        $articleRoutes = $registry->getByResourceType('articles');
        self::assertCount(1, $articleRoutes);
        self::assertSame('articles.publish', $articleRoutes[0]->name);

        $userRoutes = $registry->getByResourceType('users');
        self::assertCount(1, $userRoutes);
        self::assertSame('users.activate', $userRoutes[0]->name);

        $nonExistentRoutes = $registry->getByResourceType('products');
        self::assertCount(0, $nonExistentRoutes);
    }

    public function testConstructorWithRoutes(): void
    {
        $route = new CustomRouteMetadata(
            name: 'articles.publish',
            path: '/articles/{id}/publish',
            methods: ['POST'],
            handler: null,
            controller: 'App\Controller\PublishController',
            resourceType: 'articles',
            defaults: [],
            requirements: [],
            description: null,
            priority: 0,
        );

        $registry = new CustomRouteRegistry([$route]);

        $allRoutes = $registry->all();
        self::assertCount(1, $allRoutes);
        self::assertSame('articles.publish', $allRoutes[0]->name);
    }
}
