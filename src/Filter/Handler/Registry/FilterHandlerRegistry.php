<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Filter\Handler\Registry;

use AlexFigures\Symfony\Filter\Handler\FilterHandlerInterface;

/**
 * Registry for custom filter handlers.
 *
 * This registry manages all registered filter handlers and provides
 * methods to find the appropriate handler for a given field and operator.
 *
 * Handlers are sorted by priority (highest first) and the first matching
 * handler is used.
 *
 * @api This class is part of the public API and follows semantic versioning.
 * @since 1.1.0
 */
final class FilterHandlerRegistry
{
    /**
     * @var list<FilterHandlerInterface>
     */
    private array $handlers = [];

    /**
     * @var bool
     */
    private bool $sorted = false;

    /**
     * @param iterable<FilterHandlerInterface> $handlers
     */
    public function __construct(iterable $handlers = [])
    {
        foreach ($handlers as $handler) {
            $this->addHandler($handler);
        }
    }

    /**
     * Add a filter handler to the registry.
     */
    public function addHandler(FilterHandlerInterface $handler): void
    {
        $this->handlers[] = $handler;
        $this->sorted = false;
    }

    /**
     * Find a handler that supports the given field and operator.
     *
     * @param  string                      $field    The field name
     * @param  string                      $operator The operator
     * @return FilterHandlerInterface|null The handler or null if none found
     */
    public function findHandler(string $field, string $operator): ?FilterHandlerInterface
    {
        $this->ensureSorted();

        foreach ($this->handlers as $handler) {
            if ($handler->supports($field, $operator)) {
                return $handler;
            }
        }

        return null;
    }

    /**
     * Check if any handler supports the given field and operator.
     */
    public function hasHandler(string $field, string $operator): bool
    {
        return $this->findHandler($field, $operator) !== null;
    }

    /**
     * Get all registered handlers.
     *
     * @return list<FilterHandlerInterface>
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

        usort($this->handlers, static function (FilterHandlerInterface $a, FilterHandlerInterface $b): int {
            return $b->getPriority() <=> $a->getPriority();
        });

        $this->sorted = true;
    }
}
