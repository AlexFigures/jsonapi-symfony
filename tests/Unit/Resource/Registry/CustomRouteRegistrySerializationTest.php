<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Unit\Resource\Registry;

use JsonApi\Symfony\Resource\Metadata\CustomRouteMetadata;
use JsonApi\Symfony\Resource\Registry\CustomRouteRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Tests that CustomRouteRegistry can handle serialized array data
 * (required for Symfony container dumping).
 */
final class CustomRouteRegistrySerializationTest extends TestCase
{
    public function testConstructorAcceptsCustomRouteMetadataObjects(): void
    {
        $route = new CustomRouteMetadata(
            name: 'articles.publish',
            path: '/articles/{id}/publish',
            methods: ['POST'],
            controller: 'App\Controller\PublishController',
            resourceType: 'articles',
            defaults: [],
            requirements: [],
            description: 'Publish an article',
            priority: 0,
        );

        $registry = new CustomRouteRegistry([$route]);

        $allRoutes = $registry->all();
        self::assertCount(1, $allRoutes);
        self::assertSame('articles.publish', $allRoutes[0]->name);
    }

    public function testConstructorAcceptsSerializedArrays(): void
    {
        // This is how routes are stored in the container after discovery
        $serializedRoute = [
            'name' => 'articles.publish',
            'path' => '/articles/{id}/publish',
            'methods' => ['POST'],
            'controller' => 'App\Controller\PublishController',
            'resourceType' => 'articles',
            'defaults' => [],
            'requirements' => [],
            'description' => 'Publish an article',
            'priority' => 0,
        ];

        $registry = new CustomRouteRegistry([$serializedRoute]);

        $allRoutes = $registry->all();
        self::assertCount(1, $allRoutes);
        self::assertInstanceOf(CustomRouteMetadata::class, $allRoutes[0]);
        self::assertSame('articles.publish', $allRoutes[0]->name);
        self::assertSame('/articles/{id}/publish', $allRoutes[0]->path);
        self::assertSame(['POST'], $allRoutes[0]->methods);
        self::assertSame('App\Controller\PublishController', $allRoutes[0]->controller);
        self::assertSame('articles', $allRoutes[0]->resourceType);
        self::assertSame([], $allRoutes[0]->defaults);
        self::assertSame([], $allRoutes[0]->requirements);
        self::assertSame('Publish an article', $allRoutes[0]->description);
        self::assertSame(0, $allRoutes[0]->priority);
    }

    public function testConstructorAcceptsMixedFormats(): void
    {
        $metadataRoute = new CustomRouteMetadata(
            name: 'articles.publish',
            path: '/articles/{id}/publish',
            methods: ['POST'],
            controller: 'App\Controller\PublishController',
            resourceType: 'articles',
            defaults: [],
            requirements: [],
            description: null,
            priority: 0,
        );

        $serializedRoute = [
            'name' => 'articles.archive',
            'path' => '/articles/{id}/archive',
            'methods' => ['POST'],
            'controller' => 'App\Controller\ArchiveController',
            'resourceType' => 'articles',
            'defaults' => [],
            'requirements' => ['id' => '\d+'],
            'description' => 'Archive an article',
            'priority' => 5,
        ];

        $registry = new CustomRouteRegistry([$metadataRoute, $serializedRoute]);

        $allRoutes = $registry->all();
        self::assertCount(2, $allRoutes);
        
        // Routes should be sorted by priority (higher first)
        self::assertSame('articles.archive', $allRoutes[0]->name);
        self::assertSame(5, $allRoutes[0]->priority);
        self::assertSame('articles.publish', $allRoutes[1]->name);
        self::assertSame(0, $allRoutes[1]->priority);
    }

    public function testSerializedRouteWithOptionalFields(): void
    {
        // Test with minimal required fields
        $serializedRoute = [
            'name' => 'articles.search',
            'path' => '/articles/search',
            'methods' => ['GET'],
            'controller' => 'App\Controller\SearchController',
            // Optional fields omitted
        ];

        $registry = new CustomRouteRegistry([$serializedRoute]);

        $allRoutes = $registry->all();
        self::assertCount(1, $allRoutes);
        self::assertSame('articles.search', $allRoutes[0]->name);
        self::assertNull($allRoutes[0]->resourceType);
        self::assertSame([], $allRoutes[0]->defaults);
        self::assertSame([], $allRoutes[0]->requirements);
        self::assertNull($allRoutes[0]->description);
        self::assertSame(0, $allRoutes[0]->priority);
    }
}

