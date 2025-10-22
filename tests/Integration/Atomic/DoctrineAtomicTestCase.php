<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Atomic;

use AlexFigures\Symfony\Atomic\AtomicConfig;
use AlexFigures\Symfony\Atomic\Execution\AtomicTransaction;
use AlexFigures\Symfony\Atomic\Execution\Handlers\AddHandler;
use AlexFigures\Symfony\Atomic\Execution\Handlers\RelationshipOps;
use AlexFigures\Symfony\Atomic\Execution\Handlers\RemoveHandler;
use AlexFigures\Symfony\Atomic\Execution\Handlers\UpdateHandler;
use AlexFigures\Symfony\Atomic\Execution\OperationDispatcher;
use AlexFigures\Symfony\Atomic\Parser\AtomicRequestParser;
use AlexFigures\Symfony\Atomic\Result\ResultBuilder;
use AlexFigures\Symfony\Atomic\Validation\AtomicValidator;
use AlexFigures\Symfony\Bridge\Doctrine\ExistenceChecker\DoctrineExistenceChecker;
use AlexFigures\Symfony\Bridge\Doctrine\Relationship\GenericDoctrineRelationshipHandler;
use AlexFigures\Symfony\Bridge\Symfony\Controller\AtomicController;
use AlexFigures\Symfony\Bridge\Symfony\Negotiation\ChannelScopeMatcher;
use AlexFigures\Symfony\Bridge\Symfony\Negotiation\ConfigMediaTypePolicyProvider;
use AlexFigures\Symfony\Http\Document\DocumentBuilder;
use AlexFigures\Symfony\Http\Error\ErrorBuilder;
use AlexFigures\Symfony\Http\Error\ErrorMapper;
use AlexFigures\Symfony\Http\Link\LinkGenerator;
use AlexFigures\Symfony\Http\Negotiation\MediaType;
use AlexFigures\Symfony\Http\Negotiation\MediaTypeNegotiator;
use AlexFigures\Symfony\Http\Write\ChangeSetFactory;
use AlexFigures\Symfony\Tests\Integration\DoctrineIntegrationTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Base test case for Atomic Operations integration tests with Doctrine.
 *
 * Provides helper methods for executing atomic operations and asserting database state.
 */
abstract class DoctrineAtomicTestCase extends DoctrineIntegrationTestCase
{
    protected AtomicController $atomicController;
    protected LinkGenerator $linkGenerator;
    protected DocumentBuilder $documentBuilder;
    protected ChangeSetFactory $changeSetFactory;
    protected ErrorMapper $errorMapper;

    /**
     * Use PostgreSQL for atomic operations integration tests.
     */
    protected function getDatabaseUrl(): string
    {
        return $_ENV['DATABASE_URL_PGSQL'] ?? 'postgresql://jsonapi:jsonapi@postgres:5432/jsonapi_test?serverVersion=16&charset=utf8';
    }

    /**
     * PostgreSQL platform.
     */
    protected function getPlatform(): string
    {
        return 'postgresql';
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Initialize atomic-specific services
        $this->initializeAtomicServices();
    }

    /**
     * Initialize services required for atomic operations.
     */
    private function initializeAtomicServices(): void
    {
        // Create LinkGenerator
        $routes = $this->createRouteCollection();
        $context = new RequestContext();
        $context->setScheme('http');
        $context->setHost('localhost');
        $urlGenerator = new UrlGenerator($routes, $context);
        $this->linkGenerator = new LinkGenerator($urlGenerator);

        // Create DocumentBuilder
        $this->documentBuilder = new DocumentBuilder(
            $this->registry,
            $this->accessor,
            $this->linkGenerator,
            'always'
        );

        // Create ChangeSetFactory
        $this->changeSetFactory = new ChangeSetFactory($this->registry);

        // Create ErrorMapper
        $errorBuilder = new ErrorBuilder(true);
        $this->errorMapper = new ErrorMapper($errorBuilder);

        // Create ExistenceChecker
        $existenceChecker = new DoctrineExistenceChecker($this->managerRegistry, $this->registry);

        // Create RelationshipHandler
        $relationshipHandler = new GenericDoctrineRelationshipHandler(
            $this->managerRegistry,
            $this->registry,
            $this->accessor,
            $this->flushManager
        );

        // Create MediaTypePolicyProvider
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

        // Create AtomicConfig
        $atomicConfig = new AtomicConfig(
            enabled: true,
            endpoint: '/api/operations',
            requireExtHeader: true,
            maxOperations: 100,
            returnPolicy: 'auto',
            allowHref: true,
            lidInResourceAndIdentifier: true,
            routePrefix: '/api'
        );

        // Create MediaTypeNegotiator
        $mediaNegotiator = new MediaTypeNegotiator($atomicConfig, $mediaTypePolicyProvider);

        // Create AtomicRequestParser
        $atomicParser = new AtomicRequestParser($atomicConfig, $this->errorMapper);

        // Create AtomicValidator
        $atomicValidator = new AtomicValidator($atomicConfig, $this->registry, $this->errorMapper);

        // Create AtomicTransaction
        $atomicTransaction = new AtomicTransaction($this->transactionManager);

        // Create operation handlers
        // Use ValidatingDoctrineProcessor to support relationships
        $addHandler = new AddHandler(
            $this->validatingProcessor,
            $this->changeSetFactory,
            $this->registry,
            $this->accessor
        );

        $updateHandler = new UpdateHandler(
            $this->validatingProcessor,
            $this->changeSetFactory,
            $this->registry,
            $this->accessor,
            $this->errorMapper
        );

        $removeHandler = new RemoveHandler(
            $this->validatingProcessor,
            $this->errorMapper
        );

        $relationshipOps = new RelationshipOps(
            $relationshipHandler,
            $this->registry,
            $this->errorMapper
        );

        // Create ResultBuilder
        $resultBuilder = new ResultBuilder($atomicConfig, $this->documentBuilder);

        // Create OperationDispatcher
        $dispatcher = new OperationDispatcher(
            $atomicTransaction,
            $addHandler,
            $updateHandler,
            $removeHandler,
            $relationshipOps,
            $resultBuilder,
            $this->flushManager
        );

        // Create AtomicController
        $this->atomicController = new AtomicController(
            $atomicParser,
            $atomicValidator,
            $dispatcher,
            $mediaNegotiator
        );
    }

    /**
     * Create route collection for LinkGenerator.
     */
    private function createRouteCollection(): RouteCollection
    {
        $routes = new RouteCollection();
        $routes->add('jsonapi.collection', new Route('/api/{type}'));
        $routes->add('jsonapi.resource', new Route('/api/{type}/{id}'));
        $routes->add('jsonapi.related', new Route('/api/{type}/{id}/{rel}'));
        $routes->add('jsonapi.relationship.get', new Route('/api/{type}/{id}/relationships/{rel}'));
        $routes->add('jsonapi.relationship.write', new Route('/api/{type}/{id}/relationships/{rel}'));

        // Add type-specific routes
        foreach (['articles', 'authors', 'tags', 'comments'] as $type) {
            $routes->add("jsonapi.{$type}.index", new Route("/api/{$type}"));
            $routes->add("jsonapi.{$type}.show", new Route("/api/{$type}/{id}"));

            // Add relationship routes for all possible relationships
            foreach (['articles', 'authors', 'tags', 'comments', 'author', 'comment'] as $rel) {
                $routes->add("jsonapi.{$type}.related.{$rel}", new Route("/api/{$type}/{id}/{$rel}"));
                $routes->add("jsonapi.{$type}.relationships.{$rel}.show", new Route("/api/{$type}/{id}/relationships/{$rel}"));
            }
        }

        return $routes;
    }

    /**
     * Execute an atomic operations request.
     *
     * @param array<int, array<string, mixed>> $operations
     */
    protected function executeAtomicRequest(array $operations): Response
    {
        $payload = ['atomic:operations' => $operations];

        $request = Request::create('/api/operations', 'POST', server: [
            'CONTENT_TYPE' => MediaType::JSON_API_ATOMIC,
            'HTTP_ACCEPT' => MediaType::JSON_API_ATOMIC,
        ], content: json_encode($payload, \JSON_THROW_ON_ERROR));

        return $this->atomicController->__invoke($request);
    }

    /**
     * Assert that a resource exists in the database.
     */
    protected function assertDatabaseHasResource(string $type, string $id): void
    {
        $metadata = $this->registry->getByType($type);
        $entityClass = $metadata->class;

        $entity = $this->em->find($entityClass, $id);

        self::assertNotNull($entity, "Resource {$type}:{$id} should exist in database");
    }

    /**
     * Assert that a resource does not exist in the database.
     */
    protected function assertDatabaseMissingResource(string $type, string $id): void
    {
        $metadata = $this->registry->getByType($type);
        $entityClass = $metadata->class;

        $entity = $this->em->find($entityClass, $id);

        self::assertNull($entity, "Resource {$type}:{$id} should not exist in database");
    }

    /**
     * Assert that a to-one relationship exists in the database.
     */
    protected function assertToOneRelationshipExists(
        string $ownerType,
        string $ownerId,
        string $relationshipName,
        string $targetType,
        string $targetId
    ): void {
        $ownerMetadata = $this->registry->getByType($ownerType);
        $ownerEntity = $this->em->find($ownerMetadata->class, $ownerId);

        self::assertNotNull($ownerEntity, "Owner resource {$ownerType}:{$ownerId} should exist");

        $relationshipMetadata = $ownerMetadata->relationships[$relationshipName] ?? null;
        self::assertNotNull($relationshipMetadata, "Relationship {$relationshipName} should exist on {$ownerType}");

        $propertyPath = $relationshipMetadata->propertyPath ?? $relationshipName;
        $relatedEntity = $this->accessor->getValue($ownerEntity, $propertyPath);

        self::assertNotNull($relatedEntity, "Relationship {$relationshipName} should not be null");

        $targetMetadata = $this->registry->getByType($targetType);
        $idProperty = $targetMetadata->idPropertyPath ?? 'id';
        $actualId = (string) $this->accessor->getValue($relatedEntity, $idProperty);

        self::assertSame($targetId, $actualId, "Relationship {$relationshipName} should point to {$targetType}:{$targetId}");
    }

    /**
     * Assert that a to-many relationship contains a specific target.
     */
    protected function assertToManyRelationshipContains(
        string $ownerType,
        string $ownerId,
        string $relationshipName,
        string $targetType,
        string $targetId
    ): void {
        $ownerMetadata = $this->registry->getByType($ownerType);
        $ownerEntity = $this->em->find($ownerMetadata->class, $ownerId);

        self::assertNotNull($ownerEntity, "Owner resource {$ownerType}:{$ownerId} should exist");

        $relationshipMetadata = $ownerMetadata->relationships[$relationshipName] ?? null;
        self::assertNotNull($relationshipMetadata, "Relationship {$relationshipName} should exist on {$ownerType}");

        $propertyPath = $relationshipMetadata->propertyPath ?? $relationshipName;
        $collection = $this->accessor->getValue($ownerEntity, $propertyPath);

        self::assertIsIterable($collection, "Relationship {$relationshipName} should be iterable");

        $targetMetadata = $this->registry->getByType($targetType);
        $idProperty = $targetMetadata->idPropertyPath ?? 'id';

        $found = false;
        foreach ($collection as $item) {
            $actualId = (string) $this->accessor->getValue($item, $idProperty);
            if ($actualId === $targetId) {
                $found = true;
                break;
            }
        }

        self::assertTrue($found, "Relationship {$relationshipName} should contain {$targetType}:{$targetId}");
    }

    /**
     * Get the count of resources of a given type in the database.
     */
    protected function getDatabaseResourceCount(string $type): int
    {
        $metadata = $this->registry->getByType($type);
        $entityClass = $metadata->class;

        $qb = $this->em->createQueryBuilder();
        $qb->select('COUNT(e.id)')
            ->from($entityClass, 'e');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
