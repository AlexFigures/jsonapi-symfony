<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Contract\Tx;

/**
 * Null Object реализация TransactionManager.
 * 
 * Используется как дефолтная реализация, когда пользователь
 * не предоставил свою реализацию.
 * 
 * Выполняет callback без транзакции.
 */
final class NullTransactionManager implements TransactionManager
{
    public function transactional(callable $callback): mixed
    {
        return $callback();
    }
}

