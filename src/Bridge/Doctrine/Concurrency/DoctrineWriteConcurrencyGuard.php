<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Bridge\Doctrine\Concurrency;

use AlexFigures\Symfony\Bridge\Doctrine\Identifier\IdentifierConverter;
use AlexFigures\Symfony\Bridge\Doctrine\Transaction\DoctrineTransactionBoundaryResolver;
use AlexFigures\Symfony\Contract\Data\WriteConcurrencyGuardInterface;
use AlexFigures\Symfony\Contract\Tx\ScopedTransactionManagerInterface;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/** Narrow root-row write lock; never reuses a representation read before waiting. */
final readonly class DoctrineWriteConcurrencyGuard implements WriteConcurrencyGuardInterface
{
    public function __construct(
        private ManagerRegistry $managers,
        private ResourceRegistryInterface $resources,
        private ScopedTransactionManagerInterface $transactions,
    ) {
    }

    /** @template T
     * @param  callable():T $write
     * @return T
     */
    public function protect(string $type, string $id, callable $write): mixed
    {
        $class = $this->resources->getByType($type)->dataClass;
        $manager = (new DoctrineTransactionBoundaryResolver($this->managers))->resolve([$class]);
        \assert($manager !== null);

        return $this->transactions->transactionalFor([$class], static function () use ($manager, $class, $id, $write) {
            // Discard earlier reads (including initialized collections) in this request's identity map.
            // This runs before any write controller prepares changes.
            $manager->clear();
            $identifier = IdentifierConverter::convert($manager, $class, $id);
            $manager->find($class, $identifier, LockMode::PESSIMISTIC_WRITE);

            return $write();
        });
    }
}
