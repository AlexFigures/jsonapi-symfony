<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Unit\Filter\Handler\Registry;

use JsonApi\Symfony\Filter\Handler\Registry\SortHandlerRegistry;
use JsonApi\Symfony\Filter\Handler\SortHandlerInterface;
use PHPUnit\Framework\TestCase;

final class SortHandlerRegistryTest extends TestCase
{
    public function testEmptyRegistry(): void
    {
        $registry = new SortHandlerRegistry();

        $this->assertSame(0, $registry->count());
        $this->assertSame([], $registry->getHandlers());
        $this->assertNull($registry->findHandler('field'));
        $this->assertFalse($registry->hasHandler('field'));
    }

    public function testAddHandler(): void
    {
        $registry = new SortHandlerRegistry();
        $handler = $this->createMockHandler('field1', 0);

        $registry->addHandler($handler);

        $this->assertSame(1, $registry->count());
        $this->assertSame([$handler], $registry->getHandlers());
    }

    public function testFindHandler(): void
    {
        $registry = new SortHandlerRegistry();
        $handler1 = $this->createMockHandler('field1', 0);
        $handler2 = $this->createMockHandler('field2', 0);

        $registry->addHandler($handler1);
        $registry->addHandler($handler2);

        $this->assertSame($handler1, $registry->findHandler('field1'));
        $this->assertSame($handler2, $registry->findHandler('field2'));
        $this->assertNull($registry->findHandler('field3'));
    }

    public function testHasHandler(): void
    {
        $registry = new SortHandlerRegistry();
        $handler = $this->createMockHandler('field1', 0);

        $registry->addHandler($handler);

        $this->assertTrue($registry->hasHandler('field1'));
        $this->assertFalse($registry->hasHandler('field2'));
    }

    public function testPriorityOrdering(): void
    {
        $registry = new SortHandlerRegistry();
        $lowPriorityHandler = $this->createMockHandler('field', 1);
        $highPriorityHandler = $this->createMockHandler('field', 10);
        $mediumPriorityHandler = $this->createMockHandler('field', 5);

        // Add in random order
        $registry->addHandler($lowPriorityHandler);
        $registry->addHandler($highPriorityHandler);
        $registry->addHandler($mediumPriorityHandler);

        // Should return highest priority handler first
        $this->assertSame($highPriorityHandler, $registry->findHandler('field'));

        // Check ordering in getHandlers()
        $handlers = $registry->getHandlers();
        $this->assertSame($highPriorityHandler, $handlers[0]);
        $this->assertSame($mediumPriorityHandler, $handlers[1]);
        $this->assertSame($lowPriorityHandler, $handlers[2]);
    }

    public function testConstructorWithHandlers(): void
    {
        $handler1 = $this->createMockHandler('field1', 0);
        $handler2 = $this->createMockHandler('field2', 0);

        $registry = new SortHandlerRegistry([$handler1, $handler2]);

        $this->assertSame(2, $registry->count());
        $this->assertSame($handler1, $registry->findHandler('field1'));
        $this->assertSame($handler2, $registry->findHandler('field2'));
    }

    public function testMultipleHandlersForSameFieldReturnHighestPriority(): void
    {
        $registry = new SortHandlerRegistry();
        $lowPriorityHandler = $this->createMockHandler('field', 1);
        $highPriorityHandler = $this->createMockHandler('field', 10);

        $registry->addHandler($lowPriorityHandler);
        $registry->addHandler($highPriorityHandler);

        // Should return the highest priority handler
        $this->assertSame($highPriorityHandler, $registry->findHandler('field'));
    }

    private function createMockHandler(string $field, int $priority): SortHandlerInterface
    {
        $handler = $this->createMock(SortHandlerInterface::class);
        $handler->method('supports')->willReturnCallback(
            fn (string $f) => $f === $field
        );
        $handler->method('getPriority')->willReturn($priority);

        return $handler;
    }
}
