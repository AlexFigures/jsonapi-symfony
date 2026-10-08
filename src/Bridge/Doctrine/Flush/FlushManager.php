<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Bridge\Doctrine\Flush;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use RuntimeException;

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

    private ?EntityManagerInterface $boundary = null;

    public function restrictTo(?EntityManagerInterface $manager): void
    {
        foreach ($this->managersToFlush as $scheduled) {
            if ($manager !== null && $manager !== $scheduled) {
                throw new \AlexFigures\JsonApi\Http\Exception\UnsupportedTransactionBoundaryException();
            }
        }
        $this->boundary = $manager;
    }

    /** @var array<int, EntityManagerInterface> */
    private array $managersToFlush = [];

    /** @var array<int, class-string> */
    private array $classesToFlush = [];

    /** @var array<int, class-string> */
    private array $transactionClasses = [];

    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
        private readonly ?\AlexFigures\JsonApi\Http\Validation\DatabaseErrorMapper $errors = null,
        private readonly ?\AlexFigures\JsonApi\Resource\Registry\ResourceRegistryInterface $resources = null,
    ) {
    }

    /**
     * Schedule a flush operation to be executed later.
     *
     * This method is called by ResourceProcessor implementations after
     * preparing entities for persistence. The entity class determines which
     * Doctrine entity manager should be flushed. The actual flush will be
     * performed by WriteListener after controller execution.
     */
    /**
     * @param class-string $entityClass
     */
    public function scheduleFlush(string $entityClass): void
    {
        $em = $this->getEntityManagerFor($entityClass);
        if ($this->boundary !== null && $this->boundary !== $em) {
            throw new \AlexFigures\JsonApi\Http\Exception\UnsupportedTransactionBoundaryException();
        }
        $this->managersToFlush[spl_object_id($em)] = $em;
        $this->classesToFlush[spl_object_id($em)] = $entityClass;
        $this->transactionClasses[spl_object_id($em)] = $entityClass;
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
        if (!$this->flushScheduled) {
            return;
        }

        foreach ($this->managersToFlush as $key => $em) {
            try {
                $em->flush();
            } catch (\Doctrine\DBAL\Exception\ConstraintViolationException|\Doctrine\ORM\OptimisticLockException $exception) {
                $type = $this->resources?->getByClass($this->classesToFlush[$key])?->type;
                if ($type !== null && $this->errors !== null) {
                    throw $this->errors->mapDatabaseError($type, $exception);
                }
                throw $exception;
            }
        }

        $this->managersToFlush = [];
        $this->classesToFlush = [];
        $this->flushScheduled = false;
    }

    /**
     * Clear the scheduled flush flag without executing flush.
     *
     * This method is called by WriteListener when an exception occurs
     * to prevent flushing incomplete/invalid data.
     */
    public function clear(): void
    {
        $this->transactionClasses = [];
        $this->flushScheduled = false;
        $this->managersToFlush = [];
        $this->classesToFlush = [];
    }

    public function mapTransactionError(\Throwable $exception): \Throwable
    {
        if (!$exception instanceof \Doctrine\DBAL\Exception\ConstraintViolationException
            && !$exception instanceof \Doctrine\ORM\OptimisticLockException) {
            return $exception;
        }
        $entityClass = end($this->transactionClasses);
        $type = $entityClass === false ? null : $this->resources?->getByClass($entityClass)?->type;
        return $type !== null && $this->errors !== null ? $this->errors->mapDatabaseError($type, $exception) : $exception;
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

    /**
     * @param class-string $entityClass
     */
    private function getEntityManagerFor(string $entityClass): EntityManagerInterface
    {
        $em = $this->managerRegistry->getManagerForClass($entityClass);

        if (!$em instanceof EntityManagerInterface) {
            throw new RuntimeException(sprintf('No Doctrine ORM entity manager registered for class "%s".', $entityClass));
        }

        return $em;
    }
}
