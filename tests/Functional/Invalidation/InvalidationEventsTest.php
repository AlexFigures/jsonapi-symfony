<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Functional\Invalidation;

use JsonApi\Symfony\Events\RelationshipChangedEvent;
use JsonApi\Symfony\Events\ResourceChangedEvent;
use JsonApi\Symfony\Http\Controller\CreateResourceController;
use JsonApi\Symfony\Http\Controller\DeleteResourceController;
use JsonApi\Symfony\Http\Controller\RelationshipWriteController;
use JsonApi\Symfony\Http\Controller\UpdateResourceController;
use JsonApi\Symfony\Tests\Functional\JsonApiTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * GAP-011: Surrogate Keys & Invalidation - Events
 *
 * Tests that invalidation events are dispatched when resources are modified:
 * - ResourceChangedEvent dispatched on create
 * - ResourceChangedEvent dispatched on update
 * - ResourceChangedEvent dispatched on delete
 * - RelationshipChangedEvent dispatched on relationship update
 *
 * NOTE: These tests currently FAIL because event dispatching is not implemented.
 * Controllers do not dispatch events when resources are modified.
 * This is documented as GAP-011 in docs/conformance/gaps.md
 */
final class InvalidationEventsTest extends JsonApiTestCase
{
    public function testResourceCreateDispatchesEvent(): void
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
        $controller = $this->createControllerWithEventDispatcher($eventDispatcher);

        $payload = json_encode([
            'data' => [
                'type' => 'articles',
                'attributes' => [
                    'title' => 'New Article',
                ],
            ],
        ], \JSON_THROW_ON_ERROR);

        $request = Request::create('/api/articles', 'POST', server: ['CONTENT_TYPE' => 'application/vnd.api+json'], content: $payload);

        // Execute create
        $response = $controller($request, 'articles');

        // Verify response is successful
        self::assertSame(201, $response->getStatusCode());

        self::assertCount(1, $dispatchedEvents);
        self::assertInstanceOf(ResourceChangedEvent::class, $dispatchedEvents[0]);
        self::assertSame('articles', $dispatchedEvents[0]->type);
        self::assertSame('create', $dispatchedEvents[0]->operation);
    }

    public function testResourceUpdateDispatchesEvent(): void
    {
        // Create a mock event dispatcher
        $dispatchedEvents = [];
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')
            ->willReturnCallback(function ($event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event;
                return $event;
            });

        // Create controller with event dispatcher
        $controller = $this->createUpdateControllerWithEventDispatcher($eventDispatcher);

        $payload = json_encode([
            'data' => [
                'type' => 'articles',
                'id' => '1',
                'attributes' => [
                    'title' => 'Updated Title',
                ],
            ],
        ], \JSON_THROW_ON_ERROR);

        $request = Request::create('/api/articles/1', 'PATCH', server: ['CONTENT_TYPE' => 'application/vnd.api+json'], content: $payload);

        // Execute update
        $response = $controller($request, 'articles', '1');

        // Verify response is successful
        self::assertSame(200, $response->getStatusCode());

        self::assertCount(1, $dispatchedEvents);
        self::assertInstanceOf(ResourceChangedEvent::class, $dispatchedEvents[0]);
        self::assertSame('articles', $dispatchedEvents[0]->type);
        self::assertSame('1', $dispatchedEvents[0]->id);
        self::assertSame('update', $dispatchedEvents[0]->operation);
    }

    public function testResourceDeleteDispatchesEvent(): void
    {
        // Create a mock event dispatcher
        $dispatchedEvents = [];
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')
            ->willReturnCallback(function ($event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event;
                return $event;
            });

        // Create controller with event dispatcher
        $controller = $this->createDeleteControllerWithEventDispatcher($eventDispatcher);

        $request = Request::create('/api/articles/1', 'DELETE');
        $request->setMethod('DELETE');

        // Execute delete
        $response = $controller('articles', '1');

        // Verify response is successful
        self::assertSame(204, $response->getStatusCode());

        self::assertCount(1, $dispatchedEvents);
        self::assertInstanceOf(ResourceChangedEvent::class, $dispatchedEvents[0]);
        self::assertSame('articles', $dispatchedEvents[0]->type);
        self::assertSame('1', $dispatchedEvents[0]->id);
        self::assertSame('delete', $dispatchedEvents[0]->operation);
    }

    public function testRelationshipUpdateDispatchesEvent(): void
    {
        // Create a mock event dispatcher
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

        $request = Request::create(
            '/api/articles/1/relationships/tags',
            'POST',
            server: ['CONTENT_TYPE' => 'application/vnd.api+json'],
            content: $payload
        );

        // Execute relationship update
        $response = $controller($request, 'articles', '1', 'tags');

        // Verify response is successful
        self::assertSame(200, $response->getStatusCode());

        self::assertCount(1, $dispatchedEvents);
        self::assertInstanceOf(RelationshipChangedEvent::class, $dispatchedEvents[0]);
        self::assertSame('articles', $dispatchedEvents[0]->type);
        self::assertSame('1', $dispatchedEvents[0]->id);
        self::assertSame('tags', $dispatchedEvents[0]->relationship);
        self::assertSame('add', $dispatchedEvents[0]->operation); // POST = add operation
    }

    /**
     * Helper to create CreateResourceController with EventDispatcher.
     */
    private function createControllerWithEventDispatcher(EventDispatcherInterface $eventDispatcher): CreateResourceController
    {
        $validator = new \JsonApi\Symfony\Http\Write\InputDocumentValidator(
            $this->registry(),
            $this->writeConfig(),
            $this->errorMapper()
        );

        return new CreateResourceController(
            $this->registry(),
            $validator,
            $this->changeSetFactory(),
            $this->persister(),
            $this->transactionManager(),
            $this->documentBuilder(),
            $this->linkGenerator(),
            $this->writeConfig(),
            $this->errorMapper(),
            $this->violationMapper(),
            $eventDispatcher,
            $this->relationshipResolver()
        );
    }

    /**
     * Helper to create UpdateResourceController with EventDispatcher.
     */
    private function createUpdateControllerWithEventDispatcher(EventDispatcherInterface $eventDispatcher): UpdateResourceController
    {
        $validator = new \JsonApi\Symfony\Http\Write\InputDocumentValidator(
            $this->registry(),
            $this->writeConfig(),
            $this->errorMapper()
        );

        return new UpdateResourceController(
            $this->registry(),
            $validator,
            $this->changeSetFactory(),
            $this->persister(),
            $this->transactionManager(),
            $this->documentBuilder(),
            $this->errorMapper(),
            $this->violationMapper(),
            $eventDispatcher,
            $this->relationshipResolver()
        );
    }

    /**
     * Helper to create DeleteResourceController with EventDispatcher.
     */
    private function createDeleteControllerWithEventDispatcher(EventDispatcherInterface $eventDispatcher): DeleteResourceController
    {
        return new DeleteResourceController(
            $this->registry(),
            $this->persister(),
            $this->transactionManager(),
            $eventDispatcher
        );
    }

    /**
     * Helper to create RelationshipWriteController with EventDispatcher.
     */
    private function createRelationshipWriteControllerWithEventDispatcher(EventDispatcherInterface $eventDispatcher): RelationshipWriteController
    {
        // Initialize dependencies
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
}
