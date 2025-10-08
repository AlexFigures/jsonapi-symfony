<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Integration;

use JsonApi\Symfony\Bridge\Symfony\Routing\JsonApiRouteLoader;
use JsonApi\Symfony\Resource\Metadata\CustomRouteMetadata;
use JsonApi\Symfony\Resource\Metadata\ResourceMetadata;
use JsonApi\Symfony\Resource\Registry\CustomRouteRegistry;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;

/**
 * Integration test to verify that custom routes with high priority
 * are matched correctly and don't get overshadowed by auto-generated routes.
 */
final class CustomRoutesPriorityTest extends TestCase
{
    public function testHighPriorityCustomRouteMatchesBeforeGenericRoute(): void
    {
        // Create a mock resource registry with an 'articles' resource
        $resourceRegistry = $this->createMock(\JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface::class);
        $resourceRegistry->method('all')->willReturn([
            new ResourceMetadata(
                type: 'articles',
                class: 'App\Entity\Article',
                attributes: [],
                relationships: [],
            ),
        ]);

        // Create custom routes with different priorities
        $customRouteRegistry = new CustomRouteRegistry([
            // High priority route that should match /articles/search
            new CustomRouteMetadata(
                name: 'articles.search',
                path: '/api/articles/search',
                methods: ['GET'],
                controller: 'App\Controller\SearchController',
                resourceType: 'articles',
                defaults: [],
                requirements: [],
                description: 'Search articles',
                priority: 10, // High priority
            ),
            // Low priority route that should come after auto-generated routes
            new CustomRouteMetadata(
                name: 'articles.archive',
                path: '/api/articles/archive',
                methods: ['POST'],
                controller: 'App\Controller\ArchiveController',
                resourceType: 'articles',
                defaults: [],
                requirements: [],
                description: 'Archive articles',
                priority: 0, // Low priority
            ),
        ]);

        // Create the route loader
        $loader = new JsonApiRouteLoader(
            registry: $resourceRegistry,
            routePrefix: '/api',
            enableRelationshipRoutes: true,
            openApiConfig: [],
            docsUiConfig: [],
            routeNameGenerator: null,
            customRouteRegistry: $customRouteRegistry
        );

        // Load all routes
        $routes = $loader->load('.', 'jsonapi');

        // Create a URL matcher to test route matching
        $context = new RequestContext();
        $matcher = new UrlMatcher($routes, $context);

        // Test 1: /api/articles/search should match the custom search route, not the generic {id} route
        $match = $matcher->match('/api/articles/search');
        $this->assertSame('App\Controller\SearchController', $match['_controller']);
        $this->assertSame('articles.search', $match['_route']);

        // Test 2: /api/articles/123 should still match the generic show route
        $match = $matcher->match('/api/articles/123');
        $this->assertSame('JsonApi\Symfony\Http\Controller\ResourceController', $match['_controller']);
        $this->assertSame('jsonapi.articles.show', $match['_route']);
        $this->assertSame('123', $match['id']);

        // Test 3: /api/articles/archive should match the custom archive route
        $context->setMethod('POST');
        $matcher = new UrlMatcher($routes, $context);
        $match = $matcher->match('/api/articles/archive');
        $this->assertSame('App\Controller\ArchiveController', $match['_controller']);
        $this->assertSame('articles.archive', $match['_route']);

        // Test 4: Verify route order - high priority routes should come before auto-generated routes
        $routeNames = array_keys($routes->all());
        $searchIndex = array_search('articles.search', $routeNames);
        $showIndex = array_search('jsonapi.articles.show', $routeNames);
        $archiveIndex = array_search('articles.archive', $routeNames);

        $this->assertNotFalse($searchIndex, 'Search route should exist');
        $this->assertNotFalse($showIndex, 'Show route should exist');
        $this->assertNotFalse($archiveIndex, 'Archive route should exist');

        // High priority custom route should come before auto-generated routes
        $this->assertLessThan($showIndex, $searchIndex, 'High priority search route should come before auto-generated show route');

        // Low priority custom route should come after auto-generated routes
        $this->assertGreaterThan($showIndex, $archiveIndex, 'Low priority archive route should come after auto-generated show route');
    }
}
