<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Atomic\Execution;

use AlexFigures\Symfony\Contract\Tx\TransactionManager;

final class AtomicTransaction
{
    public function __construct(private readonly TransactionManager $transactions)
    {
    }

    /**
     * @template T
     *
     * @param callable():T $callback
     *
     * @return T
     */
    public function run(callable $callback)
    {
        return $this->transactions->transactional($callback);
    }
}
