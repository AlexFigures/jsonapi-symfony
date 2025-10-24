<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Bridge\Doctrine\ExistenceChecker;

use AlexFigures\Symfony\Contract\Data\ExistenceChecker;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use RuntimeException;

/**
 * Doctrine ORM implementation of ExistenceChecker.
 *
 * Efficiently checks if a resource exists using COUNT queries
 * without loading the full entity into memory.
 *
 * This implementation:
 * - Uses COUNT queries for optimal performance
 * - Avoids hydrating entities
 * - Handles different ID types (string, int, UUID)
 * - Works with all Doctrine-mapped entities
 *
 * @api This class is part of the public API and follows semantic versioning.
 * @since 0.4.0
 */
final class DoctrineExistenceChecker implements ExistenceChecker
{
    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
        private readonly ResourceRegistryInterface $registry,
    ) {
    }

    public function exists(string $type, string $id): bool
    {
        if (!$this->registry->hasType($type)) {
            return false;
        }

        $metadata = $this->registry->getByType($type);
        $entityClass = $metadata->getDataClass();
        $em = $this->getEntityManagerFor($entityClass);
        $classMetadata = $em->getClassMetadata($entityClass);
        $identifierField = $classMetadata->getSingleIdentifierFieldName();

        // Ensure we query the primary database, not a replica
        // This is critical for consistency: a related entity might have just been created
        // and may not yet be replicated to read replicas
        $connection = $em->getConnection();
        if ($connection instanceof PrimaryReadReplicaConnection) {
            $connection->ensureConnectedToPrimary();
        }

        // Use COUNT query for optimal performance
        $qb = $em->createQueryBuilder();
        $qb->select('COUNT(e.' . $identifierField . ')')
            ->from($entityClass, 'e')
            ->where('e.' . $identifierField . ' = :id')
            ->setParameter('id', $id);

        $count = (int) $qb->getQuery()->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * @param class-string $entityClass
     */
    private function getEntityManagerFor(string $entityClass): EntityManagerInterface
    {
        $em = $this->managerRegistry->getManagerForClass($entityClass);

        if (!$em instanceof EntityManagerInterface) {
            throw new RuntimeException(sprintf('No Doctrine ORM entity manager registered for class "%s".', $entityClass));
        }

        return $em;
    }
}
