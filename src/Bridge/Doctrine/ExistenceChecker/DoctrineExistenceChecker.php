<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Bridge\Doctrine\ExistenceChecker;

use AlexFigures\Symfony\Contract\Data\ExistenceChecker;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
use Doctrine\ORM\EntityManagerInterface;

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
        private readonly EntityManagerInterface $em,
        private readonly ResourceRegistryInterface $registry,
    ) {
    }

    public function exists(string $type, string $id): bool
    {
        if (!$this->registry->hasType($type)) {
            return false;
        }

        $metadata = $this->registry->getByType($type);
        /** @var class-string $entityClass */
        $entityClass = $metadata->dataClass;
        $classMetadata = $this->em->getClassMetadata($entityClass);
        $identifierField = $classMetadata->getSingleIdentifierFieldName();

        // Use COUNT query for optimal performance
        $qb = $this->em->createQueryBuilder();
        $qb->select('COUNT(e.' . $identifierField . ')')
            ->from($entityClass, 'e')
            ->where('e.' . $identifierField . ' = :id')
            ->setParameter('id', $id);

        $count = (int) $qb->getQuery()->getSingleScalarResult();

        return $count > 0;
    }
}

