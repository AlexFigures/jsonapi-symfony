<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Bridge\Doctrine\Transaction;

use AlexFigures\Symfony\Bridge\Doctrine\Flush\FlushManager;
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
        private readonly FlushManager $flushManager,
    ) {
    }

    public function transactional(callable $callback): mixed
    {
        $managers = $this->collectEntityManagers();

        if ($managers === []) {
            return $callback();
        }

        foreach ($managers as $manager) {
            $manager->beginTransaction();
        }

        try {
            $result = $callback();

            $this->flushManager->flush();

            foreach ($managers as $manager) {
                $manager->commit();
            }

            return $result;
        } catch (\Throwable $exception) {
            foreach (array_reverse($managers) as $manager) {
                try {
                    $manager->rollback();
                } catch (\Throwable) {
                    // Ignore rollback failures; connection may already be closed.
                }

                $manager->close();
            }

            $this->flushManager->clear();

            throw $exception;
        }
    }

    /**
     * @return list<EntityManagerInterface>
     */
    private function collectEntityManagers(): array
    {
        $managers = [];

        foreach ($this->managerRegistry->getManagers() as $manager) {
            if ($manager instanceof EntityManagerInterface) {
                $managers[] = $manager;
            }
        }

        return $managers;
    }
}
