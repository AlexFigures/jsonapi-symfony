<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Bridge\Doctrine\Transaction;

use AlexFigures\JsonApi\Http\Exception\UnsupportedTransactionBoundaryException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/** @internal One ORM unit of work on one physical connection; no cross-manager commit protocol. */
final readonly class DoctrineTransactionBoundaryResolver
{
    public function __construct(private ManagerRegistry $registry)
    {
    }

    /** @param list<class-string> $dataClasses */
    public function resolve(array $dataClasses): ?EntityManagerInterface
    {
        $boundary = null;
        foreach (array_unique($dataClasses) as $class) {
            $manager = $this->registry->getManagerForClass($class);
            if (!$manager instanceof EntityManagerInterface) {
                throw new UnsupportedTransactionBoundaryException();
            }
            if ($boundary !== null && ($boundary->getConnection() !== $manager->getConnection() || $boundary !== $manager)) {
                throw new UnsupportedTransactionBoundaryException();
            }
            $boundary = $manager;
        }

        return $boundary;
    }
}
