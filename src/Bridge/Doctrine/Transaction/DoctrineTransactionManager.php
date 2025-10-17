<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Bridge\Doctrine\Transaction;

use AlexFigures\Symfony\Contract\Tx\TransactionManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Doctrine-backed transaction manager implementation.
 *
 * Leverages Doctrine ORM's native transaction facilities.
 */
class DoctrineTransactionManager implements TransactionManager
{
    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
    ) {
    }

    public function transactional(callable $callback): mixed
    {
        $managers = [];
        foreach ($this->managerRegistry->getManagers() as $manager) {
            if ($manager instanceof EntityManagerInterface) {
                $managers[] = $manager;
            }
        }

        $wrapped = array_reduce(
            array_reverse($managers),
            static function (callable $next, EntityManagerInterface $manager): callable {
                return static function () use ($manager, $next) {
                    return $manager->wrapInTransaction($next);
                };
            },
            $callback
        );

        return $wrapped();
    }
}
