#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Standalone HTTP server for stress testing without full Symfony Kernel.
 *
 * Based on JsonApiTestCase boot() method - manually instantiates all services.
 *
 * Usage:
 *   php scripts/stress/standalone-server.php [port]
 *
 * Example:
 *   php scripts/stress/standalone-server.php 8765
 */

use AlexFigures\Symfony\Http\Controller\CollectionController;
use AlexFigures\Symfony\Http\Controller\ResourceController;
use AlexFigures\Symfony\Http\Document\DocumentBuilder;
use AlexFigures\Symfony\Http\Error\ErrorBuilder;
use AlexFigures\Symfony\Http\Error\ErrorMapper;
use AlexFigures\Symfony\Http\Link\LinkGenerator;
use AlexFigures\Symfony\Http\Request\PaginationConfig;
use AlexFigures\Symfony\Http\Request\QueryParser;
use AlexFigures\Symfony\Http\Request\SortingWhitelist;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistry;
use AlexFigures\Symfony\StressApp\StressInMemoryRepository;
use AlexFigures\Symfony\Tests\Fixtures\Model\Article;
use AlexFigures\Symfony\Tests\Fixtures\Model\Author;
use AlexFigures\Symfony\Tests\Fixtures\Model\Tag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

require_once __DIR__ . '/../../vendor/autoload.php';

$port = (int) ($argv[1] ?? 8765);

echo "🚀 Starting standalone JSON:API server on http://127.0.0.1:{$port}\n";
echo "📊 Dataset: 1000 Articles, 100 Authors, 500 Tags\n";
echo "🔧 Press Ctrl+C to stop\n\n";

// Boot services (based on JsonApiTestCase::boot())
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

$errorBuilder = new ErrorBuilder(true);
$errorMapper = new ErrorMapper($errorBuilder);

$parser = new QueryParser($registry, $pagination, $sorting, $errorMapper);

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

// Use StressInMemoryRepository with large dataset
$repository = new StressInMemoryRepository($registry, $accessor);

// Create controllers
$collectionController = new CollectionController($registry, $repository, $parser, $document);
$resourceController = new ResourceController($registry, $repository, $parser, $document);

// Router function
function handleRequest(
    Request $request,
    CollectionController $collectionController,
    ResourceController $resourceController
): Response {
    $path = $request->getPathInfo();
    $method = $request->getMethod();

    try {
        // Parse route
        if (preg_match('#^/api/([a-z]+)$#', $path, $matches)) {
            // Collection endpoint
            $type = $matches[1];

            if ($method === 'GET') {
                return $collectionController($request, $type);
            }
        } elseif (preg_match('#^/api/([a-z]+)/([0-9]+)$#', $path, $matches)) {
            // Resource endpoint
            $type = $matches[1];
            $id = $matches[2];

            if ($method === 'GET') {
                return $resourceController($request, $type, $id);
            }
        }

        // Not found
        return new Response(
            json_encode(['errors' => [['status' => '404', 'title' => 'Not Found']]], JSON_THROW_ON_ERROR),
            404,
            ['Content-Type' => 'application/vnd.api+json']
        );
    } catch (\Throwable $e) {
        return new Response(
            json_encode([
                'errors' => [[
                    'status' => '500',
                    'title' => 'Internal Server Error',
                    'detail' => $e->getMessage(),
                ]],
            ], JSON_THROW_ON_ERROR),
            500,
            ['Content-Type' => 'application/vnd.api+json']
        );
    }
}

// Check if we're being called as a router script
if (php_sapi_name() === 'cli-server') {
    // We're running as PHP built-in server router
    $request = Request::createFromGlobals();
    $response = handleRequest($request, $GLOBALS['collectionController'], $GLOBALS['resourceController']);
    $response->send();
    return;
}

// Store services in globals for router
$GLOBALS['collectionController'] = $collectionController;
$GLOBALS['resourceController'] = $resourceController;

// Start server
$command = sprintf(
    'php -S 127.0.0.1:%d %s',
    $port,
    escapeshellarg(__FILE__)
);

passthru($command);

