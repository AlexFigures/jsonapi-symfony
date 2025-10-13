<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Contract\Tx;

/**
 * Null Object implementation of TransactionManager.
 *
 * Used as the default implementation when the user
 * has not provided their own implementation.
 *
 * Executes callback without transaction.
 */
final class NullTransactionManager implements TransactionManager
{
    public function transactional(callable $callback): mixed
    {
        return $callback();
    }
}
