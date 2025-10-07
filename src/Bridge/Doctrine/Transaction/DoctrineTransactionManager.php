<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Bridge\Doctrine\Transaction;

use Doctrine\ORM\EntityManagerInterface;
use JsonApi\Symfony\Contract\Tx\TransactionManager;

/**
 * Doctrine-реализация менеджера транзакций.
 *
 * Использует встроенный механизм транзакций Doctrine ORM.
 */
class DoctrineTransactionManager implements TransactionManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function transactional(callable $callback): mixed
    {
        return $this->em->wrapInTransaction($callback);
    }
}

