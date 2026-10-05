<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Bridge\Doctrine\Repository;

use AlexFigures\Symfony\Contract\Data\ResourceIdentifier;
use AlexFigures\Symfony\Contract\Data\ResourceRepository;
use AlexFigures\Symfony\Contract\Data\Slice;
use AlexFigures\Symfony\Filter\Ast\Between;
use AlexFigures\Symfony\Filter\Ast\Comparison;
use AlexFigures\Symfony\Filter\Ast\Conjunction;
use AlexFigures\Symfony\Filter\Ast\Disjunction;
use AlexFigures\Symfony\Filter\Ast\Group;
use AlexFigures\Symfony\Filter\Ast\Node;
use AlexFigures\Symfony\Filter\Ast\NullCheck;
use AlexFigures\Symfony\Filter\Compiler\Doctrine\DoctrineFilterCompiler;
use AlexFigures\Symfony\Filter\Handler\Registry\FilterHandlerRegistry;
use AlexFigures\Symfony\Filter\Handler\Registry\SortHandlerRegistry;
use AlexFigures\Symfony\Query\Criteria;
use AlexFigures\Symfony\Query\Sorting;
use AlexFigures\Symfony\Resource\Definition\ReadProjection;
use AlexFigures\Symfony\Resource\Mapper\ReadMapperInterface;
use AlexFigures\Symfony\Resource\Metadata\ResourceMetadata;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use RuntimeException;
use Stringable;

/**
 * Generic Doctrine repository for JSON:API resources.
 *
 * Automatically handles:
 * - Filtering (full support for every operator)
 * - Sorting
 * - Pagination
 * - Root-resource pagination independent of joined row counts
 * Representation relationship loading is delegated to the optional preloader.
 */
class GenericDoctrineRepository implements ResourceRepository
{
    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
        private readonly ResourceRegistryInterface $registry,
        private readonly DoctrineFilterCompiler $filterCompiler,
        private readonly FilterHandlerRegistry $filterHandlers,
        private readonly SortHandlerRegistry $sortHandlers,
        private readonly ReadMapperInterface $readMapper,
        private readonly string $collectionSortPolicy = 'legacy',
    ) {
    }

    public function findCollection(string $type, Criteria $criteria): Slice
    {
        $metadata = $this->registry->getByType($type);
        $definition = $metadata->getDefinition();
        $entityClass = $metadata->dataClass;
        $em = $this->getEntityManagerFor($entityClass);
        $roots = $em->createQueryBuilder()->select('e')->from($entityClass, 'e');
        if ($criteria->filter !== null) {
            $this->applyCustomFilters($roots, $criteria->filter);
            $this->filterCompiler->apply($roots, $criteria->filter, $em->getConnection()->getDatabasePlatform(), $metadata);
        }
        foreach ($criteria->customConditions as $condition) {
            $condition($roots);
        }
        $this->applySorting($roots, $criteria->sort, $metadata);
        [$entities, $total] = (new \AlexFigures\Symfony\Bridge\Doctrine\Query\DoctrineRootPaginator())->paginate(
            $roots,
            ($criteria->pagination->number - 1) * $criteria->pagination->size,
            $criteria->pagination->size,
        );
        if ($definition->readProjection === ReadProjection::DTO && $entities !== []) {
            $idField = $em->getClassMetadata($entityClass)->getSingleIdentifierFieldName();
            $ids = array_map(static fn (object $entity): mixed => $em->getClassMetadata($entityClass)->getFieldValue($entity, $idField), $entities);
            $projection = $em->createQueryBuilder()->from($entityClass, 'e')->where('e.' . $idField . ' IN (:pageIds)');
            \AlexFigures\Symfony\Bridge\Doctrine\Identifier\IdentifierParameters::bind($projection, $em, $entityClass, 'pageIds', array_map(fn (mixed $id): string => (string) $this->identifierMapKey($id), $ids));
            \AlexFigures\Symfony\Bridge\Doctrine\Query\DoctrineReadProjection::apply($projection, $definition);
            $projection->addSelect('e.' . $idField . ' AS __jsonapi_root_id');
            foreach ($criteria->customConditions as $condition) {
                $condition($projection);
            }
            /** @var list<array<string, mixed>> $rows */
            $rows = $projection->getQuery()->getArrayResult();
            $byId = [];
            foreach ($rows as $row) {
                $key = $this->identifierMapKey($row['__jsonapi_root_id']);
                unset($row['__jsonapi_root_id']);
                $byId[$key] = $row;
            }
            $items = [];
            foreach ($ids as $id) {
                $key = $this->identifierMapKey($id);
                if (isset($byId[$key])) {
                    $items[] = $this->readMapper->toView($byId[$key], $definition, $criteria);
                }
            }
        } else {
            $items = array_map(fn (object $entity): object => $this->readMapper->toView($entity, $definition, $criteria), $entities);
        }
        return new Slice($items, $criteria->pagination->number, $criteria->pagination->size, $total);
    }

    public function findOne(string $type, string $id, Criteria $criteria): ?object
    {
        $metadata = $this->registry->getByType($type);
        $definition = $metadata->getDefinition();
        /** @var class-string $entityClass */
        $entityClass = $metadata->dataClass;
        $em = $this->getEntityManagerFor($entityClass);

        $id = \AlexFigures\Symfony\Bridge\Doctrine\Identifier\IdentifierConverter::convert($em, $entityClass, $id);
        $classMetadata = $em->getClassMetadata($entityClass);
        $idField = $classMetadata->getSingleIdentifierFieldName();
        if ($definition->readProjection === ReadProjection::DTO) {
            $qb = $em->createQueryBuilder()
                ->from($entityClass, 'e')
                ->where('e.' . $idField . ' = :id')
                ->setParameter('id', $id, $classMetadata->getTypeOfField($idField));

            \AlexFigures\Symfony\Bridge\Doctrine\Query\DoctrineReadProjection::apply($qb, $definition);
            foreach ($criteria->customConditions as $condition) {
                $condition($qb);
            }

            $result = $qb->getQuery()->getArrayResult();
            $row = $result[0] ?? null;

            return $row === null ? null : $this->readMapper->toView($row, $definition, $criteria);
        }

        if ($criteria->customConditions !== []) {
            $identifier = $em->getClassMetadata($entityClass)->getSingleIdentifierFieldName();
            $qb = $em->createQueryBuilder()->select('e')->from($entityClass, 'e')->where('e.' . $identifier . ' = :id')->setParameter('id', $id);
            foreach ($criteria->customConditions as $condition) {
                $condition($qb);
            }
            $entity = $qb->getQuery()->getOneOrNullResult();
        } else {
            $entity = $em->find($entityClass, $id);
        }

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
     * Resolves propertyPath aliases before creating JOINs.
     *
     * @param list<Sorting> $sorting
     */
    private function applySorting(QueryBuilder $qb, array $sorting, ResourceMetadata $metadata): void
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

            // Resolve propertyPath aliases (e.g., "specialTags.name" → "articleSpecialTags.specialTag.name")
            $resolvedField = $metadata->resolveFieldPath($sort->field);

            // Check if this is a relationship field path (e.g., "author.name")
            if (str_contains($resolvedField, '.')) {
                $segments = explode('.', $resolvedField);
                $fieldName = array_pop($segments); // Last segment is the actual field

                // Build the join path and alias
                $currentAlias = 'e';
                $fullJoinPath = '';

                $classMetadata = $qb->getEntityManager()->getClassMetadata($metadata->dataClass);
                foreach ($segments as $index => $relationshipName) {
                    if ($classMetadata->hasAssociation($relationshipName)) {
                        if ($this->collectionSortPolicy === 'reject' && $classMetadata->isCollectionValuedAssociation($relationshipName)) {
                            throw new \AlexFigures\Symfony\Http\Exception\BadRequestException('Collection sorting requires an explicit aggregate handler.', [
                                new \AlexFigures\Symfony\Http\Error\ErrorObject(null, null, '400', \AlexFigures\Symfony\Http\Error\ErrorCodes::COLLECTION_SORT_UNSUPPORTED, \AlexFigures\Symfony\Http\Error\ErrorTitles::MAP[\AlexFigures\Symfony\Http\Error\ErrorCodes::COLLECTION_SORT_UNSUPPORTED], 'Sorting through a to-many relationship requires a registered custom sort handler with explicit aggregate semantics.', new \AlexFigures\Symfony\Http\Error\ErrorSource(parameter: 'sort')),
                            ]);
                        }
                        $classMetadata = $qb->getEntityManager()->getClassMetadata($classMetadata->getAssociationTargetClass($relationshipName));
                    }
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
                $qb->addOrderBy('e.' . $resolvedField, $direction);
            }
        }
        $identifier = $qb->getEntityManager()->getClassMetadata($metadata->dataClass)->getSingleIdentifierFieldName();
        $hasIdentifier = false;
        foreach ($sorting as $sort) {
            $hasIdentifier = $hasIdentifier || $metadata->resolveFieldPath($sort->field) === $identifier;
        }
        if (!$hasIdentifier) {
            $qb->addOrderBy('e.' . $identifier, 'ASC');
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

    private function identifierMapKey(mixed $identifier): int|string
    {
        if (is_int($identifier) || is_string($identifier)) {
            return $identifier;
        }
        if ($identifier instanceof Stringable) {
            return (string) $identifier;
        }

        throw new RuntimeException('Resource identifiers must be integers, strings or Stringable objects.');
    }

    /**
     * Apply custom filter handlers to the query builder.
     *
     * This method recursively walks the filter AST and applies custom handlers
     * for fields that have them registered. Custom handlers are applied before
     * the standard filter compilation.
     */
    private function applyCustomFilters(QueryBuilder $qb, Node $filterNode): void
    {
        if ($filterNode instanceof Comparison) {
            $handler = $this->filterHandlers->findHandler($filterNode->fieldPath, $filterNode->operator);
            if ($handler !== null) {
                $handler->handle($filterNode->fieldPath, $filterNode->operator, $filterNode->values, $qb);
            }
        } elseif ($filterNode instanceof NullCheck) {
            $operator = $filterNode->isNull ? 'null' : 'nnull';
            $handler = $this->filterHandlers->findHandler($filterNode->fieldPath, $operator);
            if ($handler !== null) {
                $handler->handle($filterNode->fieldPath, $operator, [], $qb);
            }
        } elseif ($filterNode instanceof Between) {
            $handler = $this->filterHandlers->findHandler($filterNode->fieldPath, 'between');
            if ($handler !== null) {
                $handler->handle($filterNode->fieldPath, 'between', [$filterNode->from, $filterNode->to], $qb);
            }
        } elseif ($filterNode instanceof Conjunction) {
            foreach ($filterNode->children as $child) {
                $this->applyCustomFilters($qb, $child);
            }
        } elseif ($filterNode instanceof Disjunction) {
            foreach ($filterNode->children as $child) {
                $this->applyCustomFilters($qb, $child);
            }
        } elseif ($filterNode instanceof Group) {
            $this->applyCustomFilters($qb, $filterNode->expression);
        }
    }
}
