<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Functional\Invalidation;

use JsonApi\Symfony\Events\RelationshipChangedEvent;
use JsonApi\Symfony\Http\Controller\RelationshipWriteController;
use JsonApi\Symfony\Tests\Functional\JsonApiTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Comprehensive tests for RelationshipChangedEvent dispatching.
 *
 * Tests all edge cases:
 * - PATCH to-one relationship (replace)
 * - PATCH to-many relationship (replace)
 * - POST to-many relationship (add)
 * - DELETE to-many relationship (remove)
 * - Event contains correct data
 * - Event NOT dispatched on validation failure
 */
final class RelationshipEventsTest extends JsonApiTestCase
{
    public function testPatchToOneRelationshipDispatchesReplaceEvent(): void
    {
        // Create a mock event dispatcher to capture dispatched events
        $dispatchedEvents = [];
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')
            ->willReturnCallback(function ($event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event;
                return $event;
            });

        // Create controller with event dispatcher
        $controller = $this->createRelationshipWriteControllerWithEventDispatcher($eventDispatcher);

        $payload = json_encode([
            'data' => ['type' => 'authors', 'id' => '2'],
        ], \JSON_THROW_ON_ERROR);

        $request = $this->relationshipRequest('PATCH', '/api/articles/1/relationships/author', $payload);

        // Execute relationship update
        $response = $controller($request, 'articles', '1', 'author');

        // Verify response is successful
        self::assertSame(200, $response->getStatusCode());

        // Verify event was dispatched
        self::assertCount(1, $dispatchedEvents);
        self::assertInstanceOf(RelationshipChangedEvent::class, $dispatchedEvents[0]);
        self::assertSame('articles', $dispatchedEvents[0]->type);
        self::assertSame('1', $dispatchedEvents[0]->id);
        self::assertSame('author', $dispatchedEvents[0]->relationship);
        self::assertSame('replace', $dispatchedEvents[0]->operation);
    }

    public function testPatchToManyRelationshipDispatchesReplaceEvent(): void
    {
        // Create a mock event dispatcher to capture dispatched events
        $dispatchedEvents = [];
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')
            ->willReturnCallback(function ($event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event;
                return $event;
            });

        // Create controller with event dispatcher
        $controller = $this->createRelationshipWriteControllerWithEventDispatcher($eventDispatcher);

        $payload = json_encode([
            'data' => [
                ['type' => 'tags', 'id' => '1'],
                ['type' => 'tags', 'id' => '2'],
            ],
        ], \JSON_THROW_ON_ERROR);

        $request = $this->relationshipRequest('PATCH', '/api/articles/1/relationships/tags', $payload);

        // Execute relationship update
        $response = $controller($request, 'articles', '1', 'tags');

        // Verify response is successful
        self::assertSame(200, $response->getStatusCode());

        // Verify event was dispatched
        self::assertCount(1, $dispatchedEvents);
        self::assertInstanceOf(RelationshipChangedEvent::class, $dispatchedEvents[0]);
        self::assertSame('articles', $dispatchedEvents[0]->type);
        self::assertSame('1', $dispatchedEvents[0]->id);
        self::assertSame('tags', $dispatchedEvents[0]->relationship);
        self::assertSame('replace', $dispatchedEvents[0]->operation);
    }

    public function testPostToManyRelationshipDispatchesAddEvent(): void
    {
        // Create a mock event dispatcher to capture dispatched events
        $dispatchedEvents = [];
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')
            ->willReturnCallback(function ($event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event;
                return $event;
            });

        // Create controller with event dispatcher
        $controller = $this->createRelationshipWriteControllerWithEventDispatcher($eventDispatcher);

        $payload = json_encode([
            'data' => [
                ['type' => 'tags', 'id' => '3'],
            ],
        ], \JSON_THROW_ON_ERROR);

        $request = $this->relationshipRequest('POST', '/api/articles/1/relationships/tags', $payload);

        // Execute relationship update
        $response = $controller($request, 'articles', '1', 'tags');

        // Verify response is successful
        self::assertSame(200, $response->getStatusCode());

        // Verify event was dispatched
        self::assertCount(1, $dispatchedEvents);
        self::assertInstanceOf(RelationshipChangedEvent::class, $dispatchedEvents[0]);
        self::assertSame('articles', $dispatchedEvents[0]->type);
        self::assertSame('1', $dispatchedEvents[0]->id);
        self::assertSame('tags', $dispatchedEvents[0]->relationship);
        self::assertSame('add', $dispatchedEvents[0]->operation);
    }

    public function testDeleteFromToManyRelationshipDispatchesRemoveEvent(): void
    {
        // Create a mock event dispatcher to capture dispatched events
        $dispatchedEvents = [];
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')
            ->willReturnCallback(function ($event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event;
                return $event;
            });

        // Create controller with event dispatcher
        $controller = $this->createRelationshipWriteControllerWithEventDispatcher($eventDispatcher);

        $payload = json_encode([
            'data' => [
                ['type' => 'tags', 'id' => '1'],
            ],
        ], \JSON_THROW_ON_ERROR);

        $request = $this->relationshipRequest('DELETE', '/api/articles/1/relationships/tags', $payload);

        // Execute relationship update
        $response = $controller($request, 'articles', '1', 'tags');

        // Verify response is successful
        self::assertSame(200, $response->getStatusCode());

        // Verify event was dispatched
        self::assertCount(1, $dispatchedEvents);
        self::assertInstanceOf(RelationshipChangedEvent::class, $dispatchedEvents[0]);
        self::assertSame('articles', $dispatchedEvents[0]->type);
        self::assertSame('1', $dispatchedEvents[0]->id);
        self::assertSame('tags', $dispatchedEvents[0]->relationship);
        self::assertSame('remove', $dispatchedEvents[0]->operation);
    }

    public function testEventNotDispatchedOnValidationFailure(): void
    {
        // Create a mock event dispatcher to capture dispatched events
        $dispatchedEvents = [];
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')
            ->willReturnCallback(function ($event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event;
                return $event;
            });

        // Create controller with event dispatcher
        $controller = $this->createRelationshipWriteControllerWithEventDispatcher($eventDispatcher);

        $payload = json_encode([
            'invalid' => 'payload',
        ], \JSON_THROW_ON_ERROR);

        $request = $this->relationshipRequest('POST', '/api/articles/1/relationships/tags', $payload);

        // Execute relationship update - should fail validation
        try {
            $controller($request, 'articles', '1', 'tags');
            self::fail('Expected exception was not thrown');
        } catch (\Throwable) {
            // Expected - validation should fail
        }

        // Verify NO event was dispatched
        self::assertCount(0, $dispatchedEvents, 'Event should not be dispatched on validation failure');
    }

    /**
     * Helper to create RelationshipWriteController with custom EventDispatcher.
     */
    private function createRelationshipWriteControllerWithEventDispatcher(EventDispatcherInterface $eventDispatcher): RelationshipWriteController
    {
        // Initialize the test case to get all dependencies
        $this->registry();

        $relationshipValidator = new \JsonApi\Symfony\Http\Write\RelationshipDocumentValidator(
            $this->registry(),
            new \JsonApi\Symfony\Tests\Fixtures\InMemory\InMemoryExistenceChecker($this->repository()),
            $this->errorMapper()
        );

        $relationshipUpdater = new \JsonApi\Symfony\Tests\Fixtures\InMemory\InMemoryRelationshipUpdater(
            $this->registry(),
            $this->repository()
        );

        $relationshipReader = new \JsonApi\Symfony\Tests\Fixtures\InMemory\InMemoryRelationshipReader(
            $this->registry(),
            $this->repository(),
            $this->propertyAccessor()
        );

        $pagination = new \JsonApi\Symfony\Http\Request\PaginationConfig(defaultSize: 25, maxSize: 100);

        $linkageBuilder = new \JsonApi\Symfony\Http\Relationship\LinkageBuilder(
            $this->registry(),
            $relationshipReader,
            $pagination
        );

        $relationshipResponseConfig = new \JsonApi\Symfony\Http\Relationship\WriteRelationshipsResponseConfig('linkage');

        return new RelationshipWriteController(
            $relationshipValidator,
            $relationshipUpdater,
            $linkageBuilder,
            $relationshipResponseConfig,
            $this->errorMapper(),
            $this->transactionManager(),
            $eventDispatcher
        );
    }

    /**
     * Helper to create a relationship request with proper headers.
     */
    private function relationshipRequest(string $method, string $uri, string $payload): \Symfony\Component\HttpFoundation\Request
    {
        return \Symfony\Component\HttpFoundation\Request::create(
            $uri,
            $method,
            server: ['CONTENT_TYPE' => 'application/vnd.api+json'],
            content: $payload,
        );
    }
}

