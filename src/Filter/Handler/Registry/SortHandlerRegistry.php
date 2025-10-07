<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Filter\Handler\Registry;

use JsonApi\Symfony\Filter\Handler\SortHandlerInterface;

/**
 * Registry for custom sort handlers.
 *
 * This registry manages all registered sort handlers and provides
 * methods to find the appropriate handler for a given field.
 *
 * Handlers are sorted by priority (highest first) and the first matching
 * handler is used.
 *
 * @api This class is part of the public API and follows semantic versioning.
 * @since 1.1.0
 */
final class SortHandlerRegistry
{
    /**
     * @var list<SortHandlerInterface>
     */
    private array $handlers = [];

    /**
     * @var bool
     */
    private bool $sorted = false;

    /**
     * @param iterable<SortHandlerInterface> $handlers
     */
    public function __construct(iterable $handlers = [])
    {
        foreach ($handlers as $handler) {
            $this->addHandler($handler);
        }
    }

    /**
     * Add a sort handler to the registry.
     */
    public function addHandler(SortHandlerInterface $handler): void
    {
        $this->handlers[] = $handler;
        $this->sorted = false;
    }

    /**
     * Find a handler that supports the given field.
     *
     * @param string $field The field name
     * @return SortHandlerInterface|null The handler or null if none found
     */
    public function findHandler(string $field): ?SortHandlerInterface
    {
        $this->ensureSorted();

        foreach ($this->handlers as $handler) {
            if ($handler->supports($field)) {
                return $handler;
            }
        }

        return null;
    }

    /**
     * Check if any handler supports the given field.
     */
    public function hasHandler(string $field): bool
    {
        return $this->findHandler($field) !== null;
    }

    /**
     * Get all registered handlers.
     *
     * @return list<SortHandlerInterface>
     */
    public function getHandlers(): array
    {
        $this->ensureSorted();
        return $this->handlers;
    }

    /**
     * Get the number of registered handlers.
     */
    public function count(): int
    {
        return count($this->handlers);
    }

    /**
     * Ensure handlers are sorted by priority.
     */
    private function ensureSorted(): void
    {
        if ($this->sorted) {
            return;
        }

        usort($this->handlers, static function (SortHandlerInterface $a, SortHandlerInterface $b): int {
            return $b->getPriority() <=> $a->getPriority();
        });

        $this->sorted = true;
    }
}
