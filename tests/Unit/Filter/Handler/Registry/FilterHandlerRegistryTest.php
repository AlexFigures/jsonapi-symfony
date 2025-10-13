<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Filter\Handler\Registry;

use AlexFigures\Symfony\Filter\Handler\FilterHandlerInterface;
use AlexFigures\Symfony\Filter\Handler\Registry\FilterHandlerRegistry;
use PHPUnit\Framework\TestCase;

final class FilterHandlerRegistryTest extends TestCase
{
    public function testEmptyRegistry(): void
    {
        $registry = new FilterHandlerRegistry();

        $this->assertSame(0, $registry->count());
        $this->assertSame([], $registry->getHandlers());
        $this->assertNull($registry->findHandler('field', 'eq'));
        $this->assertFalse($registry->hasHandler('field', 'eq'));
    }

    public function testAddHandler(): void
    {
        $registry = new FilterHandlerRegistry();
        $handler = $this->createMockHandler('field1', 'eq', 0);

        $registry->addHandler($handler);

        $this->assertSame(1, $registry->count());
        $this->assertSame([$handler], $registry->getHandlers());
    }

    public function testFindHandler(): void
    {
        $registry = new FilterHandlerRegistry();
        $handler1 = $this->createMockHandler('field1', 'eq', 0);
        $handler2 = $this->createMockHandler('field2', 'like', 0);

        $registry->addHandler($handler1);
        $registry->addHandler($handler2);

        $this->assertSame($handler1, $registry->findHandler('field1', 'eq'));
        $this->assertSame($handler2, $registry->findHandler('field2', 'like'));
        $this->assertNull($registry->findHandler('field3', 'eq'));
        $this->assertNull($registry->findHandler('field1', 'like'));
    }

    public function testHasHandler(): void
    {
        $registry = new FilterHandlerRegistry();
        $handler = $this->createMockHandler('field1', 'eq', 0);

        $registry->addHandler($handler);

        $this->assertTrue($registry->hasHandler('field1', 'eq'));
        $this->assertFalse($registry->hasHandler('field2', 'eq'));
        $this->assertFalse($registry->hasHandler('field1', 'like'));
    }

    public function testPriorityOrdering(): void
    {
        $registry = new FilterHandlerRegistry();
        $lowPriorityHandler = $this->createMockHandler('field', 'eq', 1);
        $highPriorityHandler = $this->createMockHandler('field', 'eq', 10);
        $mediumPriorityHandler = $this->createMockHandler('field', 'eq', 5);

        // Add in random order
        $registry->addHandler($lowPriorityHandler);
        $registry->addHandler($highPriorityHandler);
        $registry->addHandler($mediumPriorityHandler);

        // Should return highest priority handler first
        $this->assertSame($highPriorityHandler, $registry->findHandler('field', 'eq'));

        // Check ordering in getHandlers()
        $handlers = $registry->getHandlers();
        $this->assertSame($highPriorityHandler, $handlers[0]);
        $this->assertSame($mediumPriorityHandler, $handlers[1]);
        $this->assertSame($lowPriorityHandler, $handlers[2]);
    }

    public function testConstructorWithHandlers(): void
    {
        $handler1 = $this->createMockHandler('field1', 'eq', 0);
        $handler2 = $this->createMockHandler('field2', 'like', 0);

        $registry = new FilterHandlerRegistry([$handler1, $handler2]);

        $this->assertSame(2, $registry->count());
        $this->assertSame($handler1, $registry->findHandler('field1', 'eq'));
        $this->assertSame($handler2, $registry->findHandler('field2', 'like'));
    }

    public function testMultipleHandlersForSameField(): void
    {
        $registry = new FilterHandlerRegistry();
        $handler1 = $this->createMockHandler('field', 'eq', 5);
        $handler2 = $this->createMockHandler('field', 'like', 10);

        $registry->addHandler($handler1);
        $registry->addHandler($handler2);

        // Different operators should return different handlers
        $this->assertSame($handler1, $registry->findHandler('field', 'eq'));
        $this->assertSame($handler2, $registry->findHandler('field', 'like'));
    }

    private function createMockHandler(string $field, string $operator, int $priority): FilterHandlerInterface
    {
        $handler = $this->createMock(FilterHandlerInterface::class);
        $handler->method('supports')->willReturnCallback(
            fn (string $f, string $o) => $f === $field && $o === $operator
        );
        $handler->method('getPriority')->willReturn($priority);

        return $handler;
    }
}
