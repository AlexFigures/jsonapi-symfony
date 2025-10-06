<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Fixtures\InMemory;

use JsonApi\Symfony\Contract\Tx\TransactionManager;

final class InMemoryTransactionManager implements TransactionManager
{
    private bool $inTransaction = false;
    /** @var list<callable> */
    private array $rollbackCallbacks = [];

    public function transactional(callable $callback)
    {
        if ($this->inTransaction) {
            // Nested transaction - just execute
            return $callback();
        }

        $this->inTransaction = true;
        $this->rollbackCallbacks = [];

        try {
            $result = $callback();
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        } finally {
            $this->inTransaction = false;
            $this->rollbackCallbacks = [];
        }
    }

    public function onRollback(callable $callback): void
    {
        if ($this->inTransaction) {
            $this->rollbackCallbacks[] = $callback;
        }
    }

    private function commit(): void
    {
        // In-memory commit - nothing to do
        $this->rollbackCallbacks = [];
    }

    private function rollback(): void
    {
        // Execute rollback callbacks in reverse order
        foreach (array_reverse($this->rollbackCallbacks) as $callback) {
            $callback();
        }
        $this->rollbackCallbacks = [];
    }
}
