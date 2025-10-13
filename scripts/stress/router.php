<?php

declare(strict_types=1);

/**
 * Router script for PHP built-in server
 * 
 * This file is used by the PHP built-in server to route requests.
 * It bootstraps the JSON:API services and handles requests.
 */

use AlexFigures\Symfony\Atomic\AtomicConfig;
use AlexFigures\Symfony\Atomic\Execution\AtomicTransaction;
use AlexFigures\Symfony\Atomic\Execution\Handlers\AddHandler;
use AlexFigures\Symfony\Atomic\Execution\Handlers\RelationshipOps;
use AlexFigures\Symfony\Atomic\Execution\Handlers\RemoveHandler;
use AlexFigures\Symfony\Atomic\Execution\Handlers\UpdateHandler;
use AlexFigures\Symfony\Atomic\Execution\OperationDispatcher;
use AlexFigures\Symfony\Atomic\Parser\AtomicRequestParser;
use AlexFigures\Symfony\Atomic\Validation\AtomicValidator;
use AlexFigures\Symfony\Bridge\Symfony\Controller\AtomicController;
use AlexFigures\Symfony\Bridge\Symfony\Negotiation\ChannelScopeMatcher;
use AlexFigures\Symfony\Bridge\Symfony\Negotiation\ConfigMediaTypePolicyProvider;
use AlexFigures\Symfony\Filter\Parser\FilterParser;
use AlexFigures\Symfony\Http\Controller\CollectionController;
use AlexFigures\Symfony\Http\Controller\CreateResourceController;
use AlexFigures\Symfony\Http\Controller\DeleteResourceController;
use AlexFigures\Symfony\Http\Controller\RelatedController;
use AlexFigures\Symfony\Http\Controller\ResourceController;
use AlexFigures\Symfony\Http\Controller\UpdateResourceController;
use AlexFigures\Symfony\Http\Document\DocumentBuilder;
use AlexFigures\Symfony\Http\Error\ErrorBuilder;
use AlexFigures\Symfony\Http\Error\ErrorMapper;
use AlexFigures\Symfony\Http\Link\LinkGenerator;
use AlexFigures\Symfony\Http\Negotiation\MediaTypeNegotiator;
use AlexFigures\Symfony\Http\Negotiation\MediaType;
use AlexFigures\Symfony\Http\Request\PaginationConfig;
use AlexFigures\Symfony\Http\Request\QueryParser;
use AlexFigures\Symfony\Http\Request\SortingWhitelist;
use AlexFigures\Symfony\Http\Validation\ConstraintViolationMapper;
use AlexFigures\Symfony\Http\Write\ChangeSetFactory;
use AlexFigures\Symfony\Http\Write\InputDocumentValidator;
use AlexFigures\Symfony\Http\Write\WriteConfig;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistry;
use AlexFigures\Symfony\StressApp\StressRepositoryFactory;
use AlexFigures\Symfony\Tests\Fixtures\InMemory\InMemoryExistenceChecker;
use AlexFigures\Symfony\Tests\Fixtures\InMemory\InMemoryPersister;
use AlexFigures\Symfony\Tests\Fixtures\InMemory\InMemoryRelationshipReader;
use AlexFigures\Symfony\Tests\Fixtures\InMemory\InMemoryTransactionManager;
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

// Boot services (based on JsonApiTestCase::boot())
$registry = new ResourceRegistry([
    Article::class,
    Author::class,
    Tag::class,
]);

$pagination = new PaginationConfig(defaultSize: 25, maxSize: 100);
$sorting = new SortingWhitelist($registry);

$errorBuilder = new ErrorBuilder(true);
$errorMapper = new ErrorMapper($errorBuilder);

$filterParser = new FilterParser();
$parser = new QueryParser($registry, $pagination, $sorting, $errorMapper, $filterParser);

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

// Use StressRepositoryFactory to create InMemoryRepository with large dataset
$repository = StressRepositoryFactory::create($registry, $accessor);

// Create additional services
$transactionManager = new InMemoryTransactionManager();
$persister = new InMemoryPersister($repository, $registry, $transactionManager, $accessor);
$relationshipReader = new InMemoryRelationshipReader($registry, $repository, $accessor);
$existenceChecker = new InMemoryExistenceChecker($repository);
$violationMapper = new ConstraintViolationMapper($registry, $errorMapper);

// Write configuration
$writeConfig = new WriteConfig(false, ['authors' => true]);
$validator = new InputDocumentValidator($registry, $writeConfig, $errorMapper);
$changeSetFactory = new ChangeSetFactory($registry);

// Atomic operations configuration
$mediaTypePolicyProvider = new ConfigMediaTypePolicyProvider(
    [
        'default' => [
            'request' => ['allowed' => [MediaType::JSON_API]],
            'response' => [
                'default' => MediaType::JSON_API,
                'negotiable' => [],
            ],
        ],
        'channels' => [],
    ],
    new ChannelScopeMatcher()
);

$atomicConfig = new AtomicConfig(true, '/api/operations', false, 100, 'auto', true, true, '/api');
$mediaNegotiator = new MediaTypeNegotiator($atomicConfig, $mediaTypePolicyProvider);
$atomicParser = new AtomicRequestParser($atomicConfig, $errorMapper);
$atomicValidator = new AtomicValidator($atomicConfig, $registry, $errorMapper);
$atomicTransaction = new AtomicTransaction($transactionManager);
$addHandler = new AddHandler($persister, $changeSetFactory, $registry, $accessor);
$updateHandler = new UpdateHandler($persister, $changeSetFactory, $registry, $accessor, $errorMapper);
$removeHandler = new RemoveHandler($persister, $errorMapper);
$relationshipOps = new RelationshipOps(
    new \AlexFigures\Symfony\Tests\Fixtures\InMemory\InMemoryRelationshipUpdater($registry, $repository),
    $registry,
    $errorMapper
);
$resultBuilder = new \AlexFigures\Symfony\Atomic\Result\ResultBuilder($atomicConfig, $document);
$dispatcher = new OperationDispatcher(
    $atomicTransaction,
    $addHandler,
    $updateHandler,
    $removeHandler,
    $relationshipOps,
    $resultBuilder
);

// Create controllers
$collectionController = new CollectionController($registry, $repository, $parser, $document);
$resourceController = new ResourceController($registry, $repository, $parser, $document);
$relatedController = new RelatedController($registry, $relationshipReader, $parser, $document);
$createController = new CreateResourceController(
    $registry,
    $validator,
    $changeSetFactory,
    $persister,
    $transactionManager,
    $document,
    $linkGenerator,
    $writeConfig,
    $errorMapper,
    $violationMapper
);
$updateController = new UpdateResourceController(
    $registry,
    $validator,
    $changeSetFactory,
    $persister,
    $transactionManager,
    $document,
    $errorMapper,
    $violationMapper
);
$deleteController = new DeleteResourceController($registry, $persister, $transactionManager);
$atomicController = new AtomicController($atomicParser, $atomicValidator, $dispatcher, $mediaNegotiator);

// Handle request
$request = Request::createFromGlobals();
$path = $request->getPathInfo();
$method = $request->getMethod();

try {
    // Parse route
    if ($path === '/api/operations') {
        // Atomic operations endpoint
        if ($method === 'POST') {
            $response = $atomicController($request);
            $response->send();
            return;
        }
    } elseif (preg_match('#^/api/([a-z]+)$#', $path, $matches)) {
        // Collection endpoint
        $type = $matches[1];

        if ($method === 'GET') {
            $response = $collectionController($request, $type);
            $response->send();
            return;
        } elseif ($method === 'POST') {
            $response = $createController($request, $type);
            $response->send();
            return;
        }
    } elseif (preg_match('#^/api/([a-z]+)/([0-9]+)/([a-z]+)$#', $path, $matches)) {
        // Related resources endpoint
        $type = $matches[1];
        $id = $matches[2];
        $rel = $matches[3];

        if ($method === 'GET') {
            $response = $relatedController($request, $type, $id, $rel);
            $response->send();
            return;
        }
    } elseif (preg_match('#^/api/([a-z]+)/([0-9]+)$#', $path, $matches)) {
        // Resource endpoint
        $type = $matches[1];
        $id = $matches[2];

        if ($method === 'GET') {
            $response = $resourceController($request, $type, $id);
            $response->send();
            return;
        } elseif ($method === 'PATCH') {
            $response = $updateController($request, $type, $id);
            $response->send();
            return;
        } elseif ($method === 'DELETE') {
            $response = $deleteController($request, $type, $id);
            $response->send();
            return;
        }
    }

    // Not found
    $response = new Response(
        json_encode(['errors' => [['status' => '404', 'title' => 'Not Found']]], JSON_THROW_ON_ERROR),
        404,
        ['Content-Type' => 'application/vnd.api+json']
    );
    $response->send();
} catch (\Throwable $e) {
    $response = new Response(
        json_encode([
            'errors' => [[
                'status' => '500',
                'title' => 'Internal Server Error',
                'detail' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]],
        ], JSON_THROW_ON_ERROR),
        500,
        ['Content-Type' => 'application/vnd.api+json']
    );
    $response->send();
}

