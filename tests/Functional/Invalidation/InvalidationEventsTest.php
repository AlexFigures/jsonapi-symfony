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
        // TODO: Implement event dispatching in CreateResourceController
        // For now, mark as incomplete
        self::markTestIncomplete('Event dispatching not yet implemented in CreateResourceController (GAP-011)');

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

        $request = Request::create('/api/articles', 'POST');
        $request->headers->set('Content-Type', 'application/vnd.api+json');
        $request->setMethod('POST');

        $payload = [
            'data' => [
                'type' => 'articles',
                'attributes' => [
                    'title' => 'New Article',
                ],
                'relationships' => [
                    'author' => [
                        'data' => ['type' => 'authors', 'id' => '1'],
                    ],
                ],
            ],
        ];

        $request->initialize([], [], [], [], [], [], json_encode($payload, \JSON_THROW_ON_ERROR));

        // Execute create
        $response = $controller($request, 'articles');

        // Verify response is successful
        self::assertSame(201, $response->getStatusCode());

        // When implemented, verify event was dispatched
        // self::assertCount(1, $dispatchedEvents);
        // self::assertInstanceOf(ResourceChangedEvent::class, $dispatchedEvents[0]);
        // self::assertSame('articles', $dispatchedEvents[0]->type);
        // self::assertSame('create', $dispatchedEvents[0]->operation);
    }

    public function testResourceUpdateDispatchesEvent(): void
    {
        // TODO: Implement event dispatching in UpdateResourceController
        self::markTestIncomplete('Event dispatching not yet implemented in UpdateResourceController (GAP-011)');

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

        $request = Request::create('/api/articles/1', 'PATCH');
        $request->headers->set('Content-Type', 'application/vnd.api+json');
        $request->setMethod('PATCH');

        $payload = [
            'data' => [
                'type' => 'articles',
                'id' => '1',
                'attributes' => [
                    'title' => 'Updated Title',
                ],
            ],
        ];

        $request->initialize([], [], [], [], [], [], json_encode($payload, \JSON_THROW_ON_ERROR));

        // Execute update
        $response = $controller($request, 'articles', '1');

        // Verify response is successful
        self::assertSame(200, $response->getStatusCode());

        // When implemented, verify event was dispatched
        // self::assertCount(1, $dispatchedEvents);
        // self::assertInstanceOf(ResourceChangedEvent::class, $dispatchedEvents[0]);
        // self::assertSame('articles', $dispatchedEvents[0]->type);
        // self::assertSame('1', $dispatchedEvents[0]->id);
        // self::assertSame('update', $dispatchedEvents[0]->operation);
    }

    public function testResourceDeleteDispatchesEvent(): void
    {
        // TODO: Implement event dispatching in DeleteResourceController
        self::markTestIncomplete('Event dispatching not yet implemented in DeleteResourceController (GAP-011)');

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

        // When implemented, verify event was dispatched
        // self::assertCount(1, $dispatchedEvents);
        // self::assertInstanceOf(ResourceChangedEvent::class, $dispatchedEvents[0]);
        // self::assertSame('articles', $dispatchedEvents[0]->type);
        // self::assertSame('1', $dispatchedEvents[0]->id);
        // self::assertSame('delete', $dispatchedEvents[0]->operation);
    }

    public function testRelationshipUpdateDispatchesEvent(): void
    {
        // TODO: Implement event dispatching in RelationshipWriteController
        self::markTestIncomplete('Event dispatching not yet implemented in RelationshipWriteController (GAP-011)');

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

        $request = Request::create('/api/articles/1/relationships/tags', 'POST');
        $request->headers->set('Content-Type', 'application/vnd.api+json');
        $request->setMethod('POST');
        $request->attributes->set('_route', 'jsonapi.relationship.write');

        $payload = [
            'data' => [
                ['type' => 'tags', 'id' => '1'],
            ],
        ];

        $request->initialize([], [], [], [], [], [], json_encode($payload, \JSON_THROW_ON_ERROR));

        // Execute relationship update
        $response = $controller($request, 'articles', '1', 'tags');

        // Verify response is successful
        self::assertSame(200, $response->getStatusCode());

        // When implemented, verify event was dispatched
        // self::assertCount(1, $dispatchedEvents);
        // self::assertInstanceOf(RelationshipChangedEvent::class, $dispatchedEvents[0]);
        // self::assertSame('articles', $dispatchedEvents[0]->type);
        // self::assertSame('1', $dispatchedEvents[0]->id);
        // self::assertSame('tags', $dispatchedEvents[0]->relationship);
        // self::assertSame('update', $dispatchedEvents[0]->operation);
    }

    /**
     * Helper to create CreateResourceController with EventDispatcher.
     *
     * @param EventDispatcherInterface $eventDispatcher
     * @phpstan-ignore-next-line
     */
    private function createControllerWithEventDispatcher(EventDispatcherInterface $eventDispatcher): CreateResourceController
    {
        // For now, return the standard controller without event dispatcher
        // When implementing, add EventDispatcher to constructor
        return $this->createController();
    }

    /**
     * Helper to create UpdateResourceController with EventDispatcher.
     *
     * @param EventDispatcherInterface $eventDispatcher
     * @phpstan-ignore-next-line
     */
    private function createUpdateControllerWithEventDispatcher(EventDispatcherInterface $eventDispatcher): UpdateResourceController
    {
        // For now, return the standard controller without event dispatcher
        return $this->updateController();
    }

    /**
     * Helper to create DeleteResourceController with EventDispatcher.
     *
     * @param EventDispatcherInterface $eventDispatcher
     * @phpstan-ignore-next-line
     */
    private function createDeleteControllerWithEventDispatcher(EventDispatcherInterface $eventDispatcher): DeleteResourceController
    {
        // For now, return the standard controller without event dispatcher
        return $this->deleteController();
    }

    /**
     * Helper to create RelationshipWriteController with EventDispatcher.
     *
     * @param EventDispatcherInterface $eventDispatcher
     * @phpstan-ignore-next-line
     */
    private function createRelationshipWriteControllerWithEventDispatcher(EventDispatcherInterface $eventDispatcher): RelationshipWriteController
    {
        // For now, return the standard controller without event dispatcher
        return $this->relationshipWriteController();
    }
}

