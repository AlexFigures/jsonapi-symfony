<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Contract\Data;

/**
 * Checks if a JSON:API resource exists without loading it.
 *
 * Implement this interface for efficient existence checks, useful for:
 * - Validating relationship targets before creating relationships
 * - Checking resource existence before operations
 * - Avoiding full resource loading when only existence matters
 *
 * Example implementation for Doctrine ORM:
 * ```php
 * final class DoctrineExistenceChecker implements ExistenceChecker
 * {
 *     public function exists(string $type, string $id): bool
 *     {
 *         $class = $this->getClassForType($type);
 *         $count = $this->em->createQueryBuilder()
 *             ->select('COUNT(e.id)')
 *             ->from($class, 'e')
 *             ->where('e.id = :id')
 *             ->setParameter('id', $id)
 *             ->getQuery()
 *             ->getSingleScalarResult();
 *         return $count > 0;
 *     }
 * }
 * ```
 *
 * @api This interface is part of the public API and follows semantic versioning.
 * @since 0.1.0
 */
interface ExistenceChecker
{
    /**
     * Check if a resource exists.
     *
     * @param string $type Resource type (e.g., 'articles')
     * @param string $id Resource identifier
     * @return bool True if the resource exists, false otherwise
     */
    public function exists(string $type, string $id): bool;
}
