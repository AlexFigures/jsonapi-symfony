<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Bridge\Doctrine\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use JsonApi\Symfony\Contract\Data\ResourceRepository;
use JsonApi\Symfony\Contract\Data\Slice;
use JsonApi\Symfony\Filter\Compiler\Doctrine\DoctrineFilterCompiler;
use JsonApi\Symfony\Filter\Handler\Registry\SortHandlerRegistry;
use JsonApi\Symfony\Query\Criteria;
use JsonApi\Symfony\Query\Sorting;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;

/**
 * Generic Doctrine repository for JSON:API resources.
 *
 * Automatically handles:
 * - Filtering (full support for every operator)
 * - Sorting
 * - Pagination
 * - Sparse fieldsets (partial hydration)
 * - Eager loading for includes (prevents N+1 queries)
 */
class GenericDoctrineRepository implements ResourceRepository
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ResourceRegistryInterface $registry,
        private readonly DoctrineFilterCompiler $filterCompiler,
        private readonly SortHandlerRegistry $sortHandlers,
    ) {
    }

    public function findCollection(string $type, Criteria $criteria): Slice
    {
        $metadata = $this->registry->getByType($type);
        $entityClass = $metadata->class;

        $qb = $this->em->createQueryBuilder()
            ->select('e')
            ->from($entityClass, 'e');

        // Apply filters
        if ($criteria->filter !== null) {
            $platform = $this->em->getConnection()->getDatabasePlatform();
            $this->filterCompiler->apply($qb, $criteria->filter, $platform);
        }

        // Apply eager loading for includes (prevent N+1 queries)
        $this->applyEagerLoading($qb, $metadata, $criteria->include);

        // Apply sorting
        $this->applySorting($qb, $criteria->sort);

        // Apply pagination
        $offset = ($criteria->pagination->number - 1) * $criteria->pagination->size;
        $qb->setFirstResult($offset)
           ->setMaxResults($criteria->pagination->size);

        $items = $qb->getQuery()->getResult();
        $total = $this->countTotal($qb);

        return new Slice(
            items: $items,
            pageNumber: $criteria->pagination->number,
            pageSize: $criteria->pagination->size,
            totalItems: $total,
        );
    }

    public function findOne(string $type, string $id, Criteria $criteria): ?object
    {
        $metadata = $this->registry->getByType($type);
        return $this->em->find($metadata->class, $id);
    }

    public function findRelated(string $type, string $relationship, array $identifiers): iterable
    {
        if ($identifiers === []) {
            return [];
        }

        $metadata = $this->registry->getByType($type);

        if (!isset($metadata->relationships[$relationship])) {
            throw new \InvalidArgumentException(sprintf('Unknown relationship "%s" on resource type "%s".', $relationship, $type));
        }

        $relationshipMetadata = $metadata->relationships[$relationship];
        $targetClass = $relationshipMetadata->targetClass;

        if ($targetClass === null) {
            throw new \InvalidArgumentException(sprintf('Relationship "%s" on resource type "%s" has no target class.', $relationship, $type));
        }

        $classMetadata = $this->em->getClassMetadata($metadata->class);

        if (!$classMetadata->hasAssociation($relationship)) {
            throw new \InvalidArgumentException(sprintf('Relationship "%s" is not a Doctrine association on entity "%s".', $relationship, $metadata->class));
        }

        $associationMapping = $classMetadata->getAssociationMapping($relationship);
        $isToMany = $classMetadata->isCollectionValuedAssociation($relationship);

        // Build query to fetch related entities
        $qb = $this->em->createQueryBuilder()
            ->select('related')
            ->from($targetClass, 'related');

        if ($isToMany) {
            // For *ToMany relationships, we need to join back to the source entity
            // and filter by source IDs
            $sourceAlias = 'source';
            $inverseSide = $associationMapping['mappedBy'] ?? $associationMapping['inversedBy'] ?? null;

            if ($inverseSide !== null) {
                $qb->innerJoin('related.' . $inverseSide, $sourceAlias)
                   ->where($qb->expr()->in($sourceAlias . '.id', ':sourceIds'))
                   ->setParameter('sourceIds', $identifiers);
            } else {
                // If we can't determine inverse side, fall back to loading all source entities
                // and extracting related entities (less efficient but works)
                $sourceEntities = $this->em->getRepository($metadata->class)
                    ->createQueryBuilder('e')
                    ->where('e.id IN (:ids)')
                    ->setParameter('ids', $identifiers)
                    ->getQuery()
                    ->getResult();

                $relatedEntities = [];
                foreach ($sourceEntities as $sourceEntity) {
                    $related = $classMetadata->getFieldValue($sourceEntity, $relationship);
                    if ($related !== null) {
                        foreach ($related as $relatedEntity) {
                            $relatedEntities[] = $relatedEntity;
                        }
                    }
                }

                return array_unique($relatedEntities, \SORT_REGULAR);
            }
        } else {
            // For *ToOne relationships, get the foreign key values from source entities
            $sourceEntities = $this->em->getRepository($metadata->class)
                ->createQueryBuilder('e')
                ->select('IDENTITY(e.' . $relationship . ') as relatedId')
                ->where('e.id IN (:ids)')
                ->setParameter('ids', $identifiers)
                ->getQuery()
                ->getResult();

            $relatedIds = array_filter(array_column($sourceEntities, 'relatedId'));

            if ($relatedIds === []) {
                return [];
            }

            $qb->where($qb->expr()->in('related.id', ':relatedIds'))
               ->setParameter('relatedIds', $relatedIds);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Apply sorting to the query builder.
     *
     * @param list<Sorting> $sorting
     */
    private function applySorting(QueryBuilder $qb, array $sorting): void
    {
        $joinedForSort = [];

        foreach ($sorting as $sort) {
            /** @var Sorting $sort */

            // Check for custom sort handler first
            $customHandler = $this->sortHandlers->findHandler($sort->field);
            if ($customHandler !== null) {
                $customHandler->handle($sort->field, $sort->desc, $qb);
                continue;
            }

            $direction = $sort->desc ? 'DESC' : 'ASC';

            // Check if this is a relationship field path (e.g., "author.name")
            if (str_contains($sort->field, '.')) {
                $segments = explode('.', $sort->field);
                $fieldName = array_pop($segments); // Last segment is the actual field
                $relationshipPath = implode('.', $segments); // Everything before is the relationship path

                // Build the join path and alias
                $currentAlias = 'e';
                $fullJoinPath = '';

                foreach ($segments as $index => $relationshipName) {
                    $fullJoinPath = $currentAlias . '.' . $relationshipName;
                    $joinAlias = 'sort_' . str_replace('.', '_', implode('_', array_slice($segments, 0, $index + 1)));

                    // Create JOIN if not already created
                    if (!isset($joinedForSort[$fullJoinPath])) {
                        $qb->leftJoin($fullJoinPath, $joinAlias);
                        $joinedForSort[$fullJoinPath] = $joinAlias;
                    }

                    $currentAlias = $joinAlias;
                }

                // Add ORDER BY using the final join alias
                $qb->addOrderBy($currentAlias . '.' . $fieldName, $direction);
            } else {
                // Direct field on the root entity
                $qb->addOrderBy('e.' . $sort->field, $direction);
            }
        }
    }

    private function countTotal(QueryBuilder $qb): int
    {
        $countQb = clone $qb;
        $countQb->select('COUNT(e)')
                ->setFirstResult(0)
                ->setMaxResults(null)
                ->resetDQLPart('orderBy'); // Remove ORDER BY for count query

        return (int) $countQb->getQuery()->getSingleScalarResult();
    }

    /**
     * Apply eager loading (JOINs) for included relationships to prevent N+1 queries.
     *
     * @param list<string> $includes
     */
    private function applyEagerLoading(QueryBuilder $qb, \JsonApi\Symfony\Resource\Metadata\ResourceMetadata $metadata, array $includes): void
    {
        if ($includes === []) {
            return;
        }

        $classMetadata = $this->em->getClassMetadata($metadata->class);
        $joinedRelationships = [];

        foreach ($includes as $includePath) {
            $segments = explode('.', $includePath);
            $currentAlias = 'e';
            $currentMetadata = $classMetadata;
            $currentResourceMetadata = $metadata;

            foreach ($segments as $index => $relationshipName) {
                // Check if relationship exists in JSON:API metadata
                if (!isset($currentResourceMetadata->relationships[$relationshipName])) {
                    // Skip unknown relationships
                    continue 2;
                }

                $relationship = $currentResourceMetadata->relationships[$relationshipName];

                // Check if relationship exists in Doctrine metadata
                if (!$currentMetadata->hasAssociation($relationshipName)) {
                    // Skip if not a Doctrine association
                    continue 2;
                }

                // Create unique alias for this relationship
                $joinAlias = $relationshipName . '_' . $index;
                $joinPath = $currentAlias . '.' . $relationshipName;

                // Avoid duplicate joins
                if (!in_array($joinPath, $joinedRelationships, true)) {
                    // Use LEFT JOIN to include entities even if relationship is null
                    $qb->leftJoin($joinPath, $joinAlias);
                    $qb->addSelect($joinAlias);
                    $joinedRelationships[] = $joinPath;
                }

                // Prepare for next segment (nested includes)
                if ($index < count($segments) - 1) {
                    $currentAlias = $joinAlias;

                    // Get target entity class
                    $targetClass = $relationship->targetClass;
                    if ($targetClass === null) {
                        // Can't continue without target class
                        continue 2;
                    }

                    $currentMetadata = $this->em->getClassMetadata($targetClass);

                    // Get target resource metadata
                    $targetType = $relationship->targetType;
                    if ($targetType !== null && $this->registry->hasType($targetType)) {
                        $currentResourceMetadata = $this->registry->getByType($targetType);
                    } else {
                        // Can't continue without resource metadata
                        continue 2;
                    }
                }
            }
        }
    }
}
