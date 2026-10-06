<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Contract\Tx;

/** Optional single-resource write capability; Atomic batches still use scoped transactionalFor(). */
interface ResourceWriteTransactionManagerInterface extends TransactionManager
{
    /** @template T
     * @param  class-string $dataClass
     * @param  callable():T $callback
     * @return T
     */
    public function transactionalWriteFor(string $type, string $dataClass, callable $callback): mixed;
}
