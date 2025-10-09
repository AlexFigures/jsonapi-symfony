<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Bridge\Doctrine\Flush;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Manages deferred flush operations for Doctrine ORM.
 * 
 * This class implements the "deferred flush" pattern where entity changes
 * are accumulated and flushed at a centralized point (typically after
 * controller execution via WriteListener).
 * 
 * Benefits:
 * - Allows Doctrine's CommitOrderCalculator to properly order entity insertions
 * - Reduces number of database round-trips (one flush per request)
 * - Enables batch operations with correct dependency ordering
 * 
 * @internal This class is used internally by the bundle's persistence layer
 */
final class FlushManager
{
    private bool $flushScheduled = false;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Schedule a flush operation to be executed later.
     * 
     * This method is called by ResourceProcessor implementations after
     * preparing entities for persistence. The actual flush will be
     * performed by WriteListener after controller execution.
     */
    public function scheduleFlush(): void
    {
        $this->flushScheduled = true;
    }

    /**
     * Execute the scheduled flush operation.
     * 
     * This method is called by WriteListener after controller execution.
     * It only flushes if a flush was previously scheduled via scheduleFlush().
     * 
     * @throws \Throwable Database errors (constraint violations, etc.)
     */
    public function flush(): void
    {
        if ($this->flushScheduled) {
            $this->em->flush();
            $this->flushScheduled = false;
        }
    }

    /**
     * Clear the scheduled flush flag without executing flush.
     * 
     * This method is called by WriteListener when an exception occurs
     * to prevent flushing incomplete/invalid data.
     */
    public function clear(): void
    {
        $this->flushScheduled = false;
    }

    /**
     * Check if a flush operation is currently scheduled.
     * 
     * @internal Used for testing and debugging
     */
    public function isFlushScheduled(): bool
    {
        return $this->flushScheduled;
    }
}

