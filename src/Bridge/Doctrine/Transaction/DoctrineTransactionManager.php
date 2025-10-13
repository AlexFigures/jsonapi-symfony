<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Bridge\Doctrine\Transaction;

use AlexFigures\Symfony\Contract\Tx\TransactionManager;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine-backed transaction manager implementation.
 *
 * Leverages Doctrine ORM's native transaction facilities.
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
