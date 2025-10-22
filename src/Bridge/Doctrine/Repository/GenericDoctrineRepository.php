<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Bridge\Doctrine\Repository;

use AlexFigures\Symfony\Contract\Data\ResourceIdentifier;
use AlexFigures\Symfony\Contract\Data\ResourceRepository;
use AlexFigures\Symfony\Contract\Data\Slice;
use AlexFigures\Symfony\Filter\Compiler\Doctrine\DoctrineFilterCompiler;
use AlexFigures\Symfony\Filter\Handler\Registry\SortHandlerRegistry;
use AlexFigures\Symfony\Query\Criteria;
use AlexFigures\Symfony\Query\Sorting;
use AlexFigures\Symfony\Resource\Definition\ReadProjection;
use AlexFigures\Symfony\Resource\Definition\ResourceDefinition;
use AlexFigures\Symfony\Resource\Mapper\ReadMapperInterface;
use AlexFigures\Symfony\Resource\Metadata\ResourceMetadata;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use RuntimeException;

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
        private readonly ManagerRegistry $managerRegistry,
        private readonly ResourceRegistryInterface $registry,
        private readonly DoctrineFilterCompiler $filterCompiler,
        private readonly SortHandlerRegistry $sortHandlers,
        private readonly ReadMapperInterface $readMapper,
    ) {
    }

    public function findCollection(string $type, Criteria $criteria): Slice
    {
        $metadata = $this->registry->getByType($type);
        $definition = $metadata->getDefinition();
        /** @var class-string $entityClass */
        $entityClass = $metadata->dataClass;
        $em = $this->getEntityManagerFor($entityClass);

        $qb = $em->createQueryBuilder()
            ->from($entityClass, 'e');

        if ($definition->readProjection === ReadProjection::DTO) {
            $this->applyDtoProjection($qb, $definition);
        } else {
            $qb->select('e');
        }

        if ($criteria->filter !== null) {
            $platform = $em->getConnection()->getDatabasePlatform();
            $this->filterCompiler->apply($qb, $criteria->filter, $platform);
        }

        if ($criteria->customConditions !== []) {
            foreach ($criteria->customConditions as $condition) {
                $condition($qb);
            }
        }

        $this->applyEagerLoading($em, $qb, $metadata, $definition, $criteria->include);
        $this->applySorting($qb, $criteria->sort);

        $offset = ($criteria->pagination->number - 1) * $criteria->pagination->size;
        $qb->setFirstResult($offset)
           ->setMaxResults($criteria->pagination->size);

        $query = $qb->getQuery();

        if ($definition->readProjection === ReadProjection::DTO) {
            /** @var list<array<string, mixed>> $rows */
            $rows = $query->getArrayResult();
            $items = [];
            foreach ($rows as $row) {
                $items[] = $this->readMapper->toView($row, $definition, $criteria);
            }
        } else {
            /** @var list<object> $rows */
            $rows = $query->getResult();
            $items = [];
            foreach ($rows as $entity) {
                $items[] = $this->readMapper->toView($entity, $definition, $criteria);
            }
        }

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
        $definition = $metadata->getDefinition();
        /** @var class-string $entityClass */
        $entityClass = $metadata->dataClass;
        $em = $this->getEntityManagerFor($entityClass);

        if ($definition->readProjection === ReadProjection::DTO) {
            $qb = $em->createQueryBuilder()
                ->from($entityClass, 'e')
                ->where('e.id = :id')
                ->setParameter('id', $id);

            $this->applyDtoProjection($qb, $definition);

            $result = $qb->getQuery()->getArrayResult();
            $row = $result[0] ?? null;

            return $row === null ? null : $this->readMapper->toView($row, $definition, $criteria);
        }

        $entity = $em->find($entityClass, $id);

        return $entity === null ? null : $this->readMapper->toView($entity, $definition, $criteria);
    }

    /**
     * @param list<ResourceIdentifier> $identifiers
     *
     * @return list<object>
     */
    public function findRelated(string $type, string $relationship, array $identifiers): iterable
    {
        if ($identifiers === []) {
            return [];
        }

        $metadata = $this->registry->getByType($type);
        /** @var class-string $entityClass */
        $entityClass = $metadata->dataClass;
        $em = $this->getEntityManagerFor($entityClass);

        if (!isset($metadata->relationships[$relationship])) {
            throw new \InvalidArgumentException(sprintf('Unknown relationship "%s" on resource type "%s".', $relationship, $type));
        }

        $normalizedIds = [];
        foreach ($identifiers as $identifier) {
            if ($identifier->type !== $type) {
                throw new \InvalidArgumentException(sprintf('Identifier of type "%s" cannot be used to load "%s" resources.', $identifier->type, $type));
            }

            $normalizedIds[] = $identifier->id;
        }

        $classMetadata = $em->getClassMetadata($entityClass);

        if (!$classMetadata->hasAssociation($relationship)) {
            throw new \InvalidArgumentException(sprintf('Relationship "%s" is not a Doctrine association on entity "%s".', $relationship, $entityClass));
        }

        $identifierField = $classMetadata->getSingleIdentifierFieldName();

        $qb = $em->createQueryBuilder()
            ->select('related')
            ->from($entityClass, 'source')
            ->innerJoin('source.' . $relationship, 'related');

        $qb->where($qb->expr()->in('source.' . $identifierField, ':sourceIds'))
            ->setParameter('sourceIds', $normalizedIds);

        /** @var list<object> $results */
        $results = $qb->getQuery()->getResult();

        return $this->ensureObjectList($results, sprintf('related entities for relationship "%s" on resource "%s"', $relationship, $type));
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
        $rootAliases = $countQb->getRootAliases();
        $rootAlias = $rootAliases[0] ?? 'e';

        $countQb->select(sprintf('COUNT(DISTINCT %s)', $rootAlias))
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
    private function applyEagerLoading(EntityManagerInterface $em, QueryBuilder $qb, ResourceMetadata $metadata, ResourceDefinition $definition, array $includes): void
    {
        if ($includes === []) {
            return;
        }

        $classMetadata = $em->getClassMetadata($metadata->dataClass);
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
                    if ($definition->readProjection !== ReadProjection::DTO) {
                        $qb->addSelect($joinAlias);
                    }
                    $joinedRelationships[] = $joinPath;
                }

                // Prepare for next segment (nested includes)
                if ($index < count($segments) - 1) {
                    $currentAlias = $joinAlias;

                    // Get target entity class
                    $targetResourceMetadata = null;
                    if ($relationship->targetType !== null && $this->registry->hasType($relationship->targetType)) {
                        $targetResourceMetadata = $this->registry->getByType($relationship->targetType);
                        $targetClass = $targetResourceMetadata->dataClass;
                    } elseif ($relationship->targetClass !== null) {
                        $targetClass = $relationship->targetClass;
                        $targetResourceMetadata = $this->registry->getByClass($targetClass);
                    } else {
                        continue 2;
                    }

                    if (!class_exists($targetClass) && !interface_exists($targetClass)) {
                        continue 2;
                    }

                    /** @var class-string $targetClass */
                    $this->getEntityManagerFor($targetClass, $em);
                    $currentMetadata = $em->getClassMetadata($targetClass);

                    if ($targetResourceMetadata === null) {
                        continue 2;
                    }

                    $currentResourceMetadata = $targetResourceMetadata;
                }
            }
        }
    }

    private function applyDtoProjection(QueryBuilder $qb, ResourceDefinition $definition): void
    {
        $selects = [];

        foreach ($definition->fieldMap as $field => $expression) {
            $selects[] = sprintf('%s AS %s', $expression, $field);
        }

        if ($selects === []) {
            $qb->select('e');

            return;
        }

        $first = array_shift($selects);
        $qb->select($first);

        foreach ($selects as $select) {
            $qb->addSelect($select);
        }
    }

    /**
     * @param class-string $entityClass
     */
    private function getEntityManagerFor(string $entityClass, ?EntityManagerInterface $expected = null): EntityManagerInterface
    {
        $em = $this->managerRegistry->getManagerForClass($entityClass);

        if (!$em instanceof EntityManagerInterface) {
            throw new RuntimeException(sprintf('No Doctrine ORM entity manager registered for class "%s".', $entityClass));
        }

        if ($expected !== null && $em !== $expected) {
            throw new RuntimeException(sprintf(
                'Entity manager mismatch for class "%s". Cross-entity-manager relationships are not supported.',
                $entityClass
            ));
        }

        return $em;
    }

    /**
     * @param array<int|string, mixed> $results
     *
     * @return list<object>
     */
    private function ensureObjectList(array $results, string $context): array
    {
        $objects = [];
        foreach ($results as $result) {
            if (!is_object($result)) {
                throw new RuntimeException(sprintf('Expected list of objects for %s, got %s.', $context, get_debug_type($result)));
            }

            $objects[] = $result;
        }

        return $objects;
    }
}
