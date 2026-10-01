<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Contract\Tx;

/** Optional extension: resolve the entire persistence boundary before running the callback. */
interface ScopedTransactionManagerInterface extends TransactionManager
{
    /** @template T
     * @param  list<class-string> $dataClasses
     * @param  callable():T       $callback
     * @return T
     */
    public function transactionalFor(array $dataClasses, callable $callback): mixed;
}
