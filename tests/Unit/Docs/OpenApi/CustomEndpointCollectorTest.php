<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Docs\OpenApi;

use AlexFigures\Symfony\Docs\Attribute\OpenApiEndpoint;
use AlexFigures\Symfony\Docs\Attribute\OpenApiResponse;
use AlexFigures\Symfony\Docs\OpenApi\CustomEndpointCollector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

final class CustomEndpointCollectorTest extends TestCase
{
    public function testCollectReturnsEmptyArrayWhenNoRoutesHaveOpenApiAttribute(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $routes = new RouteCollection();
        $routes->add('test', new Route('/test', ['_controller' => 'App\\Controller\\TestController::index']));

        $router->method('getRouteCollection')->willReturn($routes);

        $collector = new CustomEndpointCollector($router);
        $endpoints = $collector->collect();

        self::assertSame([], $endpoints);
    }

    public function testCollectSkipsRoutesWithoutController(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $routes = new RouteCollection();
        $routes->add('test', new Route('/test'));

        $router->method('getRouteCollection')->willReturn($routes);

        $collector = new CustomEndpointCollector($router);
        $endpoints = $collector->collect();

        self::assertSame([], $endpoints);
    }

    public function testCollectSkipsRoutesWithInvalidControllerFormat(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $routes = new RouteCollection();
        $routes->add('test', new Route('/test', ['_controller' => 'InvalidFormat']));

        $router->method('getRouteCollection')->willReturn($routes);

        $collector = new CustomEndpointCollector($router);
        $endpoints = $collector->collect();

        self::assertSame([], $endpoints);
    }

    public function testCollectSkipsNonExistentControllerClasses(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $routes = new RouteCollection();
        $routes->add('test', new Route('/test', ['_controller' => 'NonExistent\\Controller::index']));

        $router->method('getRouteCollection')->willReturn($routes);

        $collector = new CustomEndpointCollector($router);
        $endpoints = $collector->collect();

        self::assertSame([], $endpoints);
    }

    public function testCollectCachesResults(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $routes = new RouteCollection();

        $router->expects(self::once())
            ->method('getRouteCollection')
            ->willReturn($routes);

        $collector = new CustomEndpointCollector($router);
        $collector->collect();
        $collector->collect(); // Second call should use cache
    }
}

