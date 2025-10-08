<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Unit\Resource\Attribute;

use JsonApi\Symfony\Resource\Attribute\JsonApiCustomRoute;
use PHPUnit\Framework\TestCase;

final class JsonApiCustomRouteTest extends TestCase
{
    public function testBasicConstruction(): void
    {
        $route = new JsonApiCustomRoute(
            name: 'articles.publish',
            path: '/articles/{id}/publish',
            methods: ['POST'],
            controller: 'App\Controller\PublishController',
            resourceType: 'articles'
        );

        self::assertSame('articles.publish', $route->name);
        self::assertSame('/articles/{id}/publish', $route->path);
        self::assertSame(['POST'], $route->methods);
        self::assertSame('App\Controller\PublishController', $route->controller);
        self::assertSame('articles', $route->resourceType);
        self::assertSame([], $route->defaults);
        self::assertSame([], $route->requirements);
        self::assertNull($route->description);
        self::assertSame(0, $route->priority);
    }

    public function testWithAllParameters(): void
    {
        $route = new JsonApiCustomRoute(
            name: 'articles.search',
            path: '/articles/search',
            methods: ['GET', 'HEAD'],
            controller: 'App\Controller\SearchController',
            resourceType: 'articles',
            defaults: ['_format' => 'json'],
            requirements: ['q' => '.+'],
            description: 'Search articles',
            priority: 10
        );

        self::assertSame('articles.search', $route->name);
        self::assertSame('/articles/search', $route->path);
        self::assertSame(['GET', 'HEAD'], $route->methods);
        self::assertSame('App\Controller\SearchController', $route->controller);
        self::assertSame('articles', $route->resourceType);
        self::assertSame(['_format' => 'json'], $route->defaults);
        self::assertSame(['q' => '.+'], $route->requirements);
        self::assertSame('Search articles', $route->description);
        self::assertSame(10, $route->priority);
    }

    public function testDefaultMethods(): void
    {
        $route = new JsonApiCustomRoute(
            name: 'articles.publish',
            path: '/articles/{id}/publish',
            controller: 'App\Controller\PublishController'
        );

        self::assertSame(['GET'], $route->methods);
    }

    public function testThrowsExceptionWhenBothControllerAndResourceTypeAreNull(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Either controller or resourceType must be specified for JsonApiCustomRoute');

        new JsonApiCustomRoute(
            name: 'test.route',
            path: '/test'
        );
    }

    public function testControllerOnlyIsValid(): void
    {
        $route = new JsonApiCustomRoute(
            name: 'test.route',
            path: '/test',
            controller: 'App\Controller\TestController'
        );

        self::assertSame('App\Controller\TestController', $route->controller);
        self::assertNull($route->resourceType);
    }

    public function testResourceTypeOnlyIsValid(): void
    {
        $route = new JsonApiCustomRoute(
            name: 'test.route',
            path: '/test',
            resourceType: 'articles'
        );

        self::assertNull($route->controller);
        self::assertSame('articles', $route->resourceType);
    }
}
