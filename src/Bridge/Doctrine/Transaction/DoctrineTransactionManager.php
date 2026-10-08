<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Bridge\Doctrine\Transaction;

use AlexFigures\JsonApi\Bridge\Doctrine\Flush\FlushManager;
use AlexFigures\JsonApi\Contract\Tx\ScopedTransactionManagerInterface;
use AlexFigures\JsonApi\Http\Exception\UnsupportedTransactionBoundaryException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/** @internal */
class DoctrineTransactionManager implements ScopedTransactionManagerInterface, \AlexFigures\JsonApi\Contract\Tx\ResourceWriteTransactionManagerInterface
{
    private ?EntityManagerInterface $active = null;

    /** @param iterable<\AlexFigures\JsonApi\Contract\Data\ResourcePersister> $persisters */
    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
        private readonly FlushManager $flushManager,
        private readonly iterable $persisters = [],
    ) {
    }

    public function transactionalWriteFor(string $type, string $dataClass, callable $callback): mixed
    {
        $manager = $this->managerRegistry->getManagerForClass($dataClass);
        if ($manager instanceof EntityManagerInterface) {
            return $this->run($manager, $callback);
        }
        foreach ($this->persisters as $persister) {
            if ($persister instanceof \AlexFigures\JsonApi\Contract\Data\TypedResourcePersister && $persister->supports($type)) {
                if ($this->active !== null) {
                    throw new UnsupportedTransactionBoundaryException();
                }
                // A non-ORM legacy persister owns its single-write persistence guarantees.
                // Never enlist an unrelated ORM manager on its behalf.
                return $callback();
            }
        }
        throw new \LogicException(sprintf('No Doctrine ORM entity manager or typed persister registered for resource "%s" (%s).', $type, $dataClass));
    }

    /** Legacy callers without resource context use only the default manager. */
    public function transactional(callable $callback): mixed
    {
        $manager = $this->active ?? $this->managerRegistry->getManager();

        return $manager instanceof EntityManagerInterface ? $this->run($manager, $callback) : $callback();
    }

    /** @template T
     * @param  list<class-string> $dataClasses
     * @param  callable():T       $callback
     * @return T
     */
    public function transactionalFor(array $dataClasses, callable $callback): mixed
    {
        $manager = (new DoctrineTransactionBoundaryResolver($this->managerRegistry))->resolve($dataClasses);

        return $manager === null ? $callback() : $this->run($manager, $callback);
    }

    /** @template T
     * @param  callable():T $callback
     * @return T
     */
    private function run(EntityManagerInterface $manager, callable $callback): mixed
    {
        if ($this->active !== null) {
            if ($this->active !== $manager || $this->active->getConnection() !== $manager->getConnection()) {
                throw new UnsupportedTransactionBoundaryException();
            }
            // Nested resource writes participate in the outer transaction, never commit it.
            $result = $callback();
            $this->flushManager->flush();
            return $result;
        }

        $this->flushManager->restrictTo($manager);
        $this->active = $manager;
        $started = false;
        try {
            $manager->beginTransaction();
            $started = true;
            $result = $callback();
            $this->flushManager->flush();
            try {
                $manager->commit();
            } catch (\Doctrine\DBAL\Driver\Exception $exception) {
                throw $manager->getConnection()->getDriver()->getExceptionConverter()->convert($exception, null);
            }

            return $result;
        } catch (\Throwable $exception) {
            if ($started) {
                try {
                    $manager->rollback();
                } catch (\Throwable) {
                    // Preserve the original error if the connection has already rolled back.
                }
                $manager->close();
            }
            throw $this->flushManager->mapTransactionError($exception);
        } finally {
            $this->flushManager->clear();
            $this->flushManager->restrictTo(null);
            $this->active = null;
        }
    }
}
