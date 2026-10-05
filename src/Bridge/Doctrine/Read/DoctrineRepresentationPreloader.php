<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Bridge\Doctrine\Read;

use AlexFigures\Symfony\Bridge\Doctrine\Identifier\IdentifierParameters;
use AlexFigures\Symfony\Contract\Data\RepresentationPreloaderInterface;
use AlexFigures\Symfony\Http\Document\Fetch\RepresentationFetchPlanner;
use AlexFigures\Symfony\Http\Error\ErrorMapper;
use AlexFigures\Symfony\Http\Exception\BadRequestException;
use AlexFigures\Symfony\Profile\ProfileContext;
use AlexFigures\Symfony\Query\Criteria;
use AlexFigures\Symfony\Query\Fetch\RelationshipReadMap;
use AlexFigures\Symfony\Resource\Definition\ReadProjection;
use AlexFigures\Symfony\Resource\Mapper\ReadMapperInterface;
use AlexFigures\Symfony\Resource\Metadata\ResourceMetadata;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/** @internal Bounded breadth-first loading; no service-level request state or collection fetch joins. */
final readonly class DoctrineRepresentationPreloader implements RepresentationPreloaderInterface
{
    /** @param array<string, int> $limits */
    public function __construct(
        private ManagerRegistry $managers,
        private ResourceRegistryInterface $registry,
        private PropertyAccessorInterface $accessor,
        private ReadMapperInterface $mapper,
        private RepresentationFetchPlanner $planner,
        private ErrorMapper $errors,
        private array $limits,
    ) {
    }

    public function preload(string $type, array $models, Criteria $criteria, Request $request): RelationshipReadMap
    {
        $map = new RelationshipReadMap();
        // Write responses may contain unflushed in-memory changes. Their existing serialization is retained.
        $isRead = in_array($request->getMethod(), ['GET', 'HEAD'], true);
        if (!$isRead && !$request->attributes->getBoolean('_jsonapi_representation_flushed')) {
            return $map;
        }
        $context = ProfileContext::fromRequest($request);
        /** @var array<string, array<string, true>> $known Primary and already reserved included identities. */
        $known = [];
        foreach ($models as $model) {
            $id = $this->id($this->registry->getByType($type), $model);
            $known[$type][$id] = true;
            $map->remember($type, $id, $model);
        }
        $includedCount = 0;
        $linkageCount = 0;
        $queue = [[$type, $models, $this->planner->includeTree($criteria)]];
        for ($cursor = 0; $cursor < count($queue); ++$cursor) {
            [$ownerType, $owners, $tree] = $queue[$cursor];
            if ($owners === []) {
                continue;
            }
            $metadata = $this->registry->getByType($ownerType);
            $em = $this->managers->getManagerForClass($metadata->dataClass);
            if (!$em instanceof EntityManagerInterface) {
                continue; // Optional capability for custom providers.
            }
            $ownerIds = array_values(array_unique(array_map(fn (object $model): string => $this->id($metadata, $model), $owners)));
            foreach ($this->planner->edges($metadata, $criteria, $tree, $context) as $edge) {
                $path = $edge->relationship->aliasPath ?? $edge->relationship->propertyPath ?? $edge->relationship->name;
                $class = $em->getClassMetadata($metadata->dataClass);
                $supported = true;
                /** @var array<string, string|\UnitEnum> $orderBy */
                $orderBy = [];
                foreach (explode('.', $path) as $segment) {
                    if (!$class->hasAssociation($segment)) {
                        $supported = false;
                        break;
                    }
                    $association = $class->getAssociationMapping($segment);
                    /** @var array<string, string|\UnitEnum> $orderBy */
                    $orderBy = $association['orderBy'] ?? [];
                    $class = $em->getClassMetadata($class->getAssociationTargetClass($segment));
                }
                if (!$supported) {
                    continue; // Computed application relationships retain the existing property reader.
                }
                $target = $edge->relationship->targetType !== null
                    ? $this->registry->getByType($edge->relationship->targetType)
                    : $this->registry->getByClass($class->getName());
                if ($target === null || $target->dataClass !== $class->getName()) {
                    continue;
                }
                if ($this->managers->getManagerForClass($target->dataClass) !== $em) {
                    throw new \LogicException('Doctrine relationship loading requires the same entity manager for both ends.');
                }
                $targetField = $class->getSingleIdentifierFieldName();
                $name = $edge->relationship->name;
                $newIds = [];
                if ($edge->includeChildren !== null) {
                    // DISTINCT target IDs only: bound output before hydrating even one target model.
                    foreach (array_chunk($ownerIds, 256) as $chunk) {
                        [$query, $alias] = $this->edgeQuery($em, $metadata, $path, $chunk);
                        $query->select('DISTINCT ' . $alias . '.' . $targetField . ' AS target_id')->andWhere($alias . '.' . $targetField . ' IS NOT NULL');
                        $excluded = array_map('strval', array_keys($known[$target->type] ?? []));
                        if ($excluded !== []) {
                            $query->andWhere($alias . '.' . $targetField . ' NOT IN (:known)');
                            IdentifierParameters::bind($query, $em, $target->dataClass, 'known', $excluded);
                        }
                        $limit = $isRead ? ($this->limits['included_max_resources'] ?? 1000) : 0;
                        if ($limit > 0) {
                            $query->setMaxResults($limit - $includedCount + 1);
                        }
                        foreach ($this->scalarRows($query) as $row) {
                            $id = (string) $row['target_id'];
                            $known[$target->type][$id] = true;
                            $newIds[] = $id;
                            ++$includedCount;
                            if ($limit > 0 && $includedCount > $limit) {
                                throw new BadRequestException('Too many included resources.', [$this->errors->includedResourcesLimit($limit)]);
                            }
                        }
                    }
                }
                if ($edge->count) {
                    foreach (array_chunk($ownerIds, 256) as $chunk) {
                        [$query, $alias] = $this->edgeQuery($em, $metadata, $path, $chunk);
                        $ownerField = $em->getClassMetadata($metadata->dataClass)->getSingleIdentifierFieldName();
                        // LEFT JOIN includes owners without targets (COUNT returns zero).
                        $query->select('source.' . $ownerField . ' AS owner_id', 'COUNT(DISTINCT ' . $alias . '.' . $targetField . ') AS related_count')->groupBy('source.' . $ownerField);
                        foreach ($this->scalarRows($query) as $row) {
                            $map->putCount($ownerType, (string) $row['owner_id'], $name, (int) (string) $row['related_count']);
                        }
                    }
                }
                if ($edge->linkage || $edge->includeChildren !== null) {
                    $missing = array_values(array_filter($ownerIds, static fn (string $id): bool => $map->identifiers($ownerType, $id, $name) === null));
                    foreach (array_chunk($missing, 256) as $chunk) {
                        [$query, $alias] = $this->edgeQuery($em, $metadata, $path, $chunk);
                        $ownerField = $em->getClassMetadata($metadata->dataClass)->getSingleIdentifierFieldName();
                        $query->select('DISTINCT source.' . $ownerField . ' AS owner_id', $alias . '.' . $targetField . ' AS target_id');
                        $query->andWhere($alias . '.' . $targetField . ' IS NOT NULL');
                        $query->orderBy('source.' . $ownerField, 'ASC');
                        foreach ($orderBy as $field => $direction) {
                            // PostgreSQL DISTINCT requires ORDER BY expressions in the select list.
                            $query->addSelect($alias . '.' . $field . ' AS ordering_' . $field);
                            $query->addOrderBy($alias . '.' . $field, $direction instanceof \UnitEnum ? ($direction->name === 'Ascending' ? 'ASC' : 'DESC') : strtoupper($direction));
                        }
                        if (!isset($orderBy[$targetField])) {
                            $query->addOrderBy($alias . '.' . $targetField, 'ASC');
                        }
                        $limit = $isRead ? ($this->limits['relationship_max_identifiers'] ?? 10000) : 0;
                        if ($limit > 0) {
                            $query->setMaxResults($limit - $linkageCount + 1);
                        }
                        $byOwner = array_fill_keys($chunk, []);
                        foreach ($this->scalarRows($query) as $row) {
                            ++$linkageCount;
                            if ($limit > 0 && $linkageCount > $limit) {
                                throw new BadRequestException('Relationship identifier budget exceeded.', [$this->errors->invalidParameter('fields', sprintf('Relationship loading cannot exceed %d identifiers per document. Use sparse fields or a safer linkage policy.', $limit), code: \AlexFigures\Symfony\Http\Error\ErrorCodes::RELATIONSHIP_IDENTIFIERS_LIMIT)]);
                            }
                            $targetId = (string) $row['target_id'];
                            // A target may have appeared between the budget probe and linkage query.
                            if ($edge->includeChildren !== null && !isset($known[$target->type][$targetId])) {
                                $known[$target->type][$targetId] = true;
                                $newIds[] = $targetId;
                                ++$includedCount;
                                $includeLimit = $isRead ? ($this->limits['included_max_resources'] ?? 1000) : 0;
                                if ($includeLimit > 0 && $includedCount > $includeLimit) {
                                    throw new BadRequestException('Too many included resources.', [$this->errors->includedResourcesLimit($includeLimit)]);
                                }
                            }
                            $byOwner[(string) $row['owner_id']][] = ['type' => $target->type, 'id' => $targetId];
                        }
                        foreach ($byOwner as $id => $identifiers) {
                            $map->put($ownerType, (string) $id, $name, $identifiers);
                        }
                    }
                }
                if ($edge->includeChildren !== null) {
                    $this->loadModels($em, $target, $newIds, $criteria, $map, $context);
                    $related = [];
                    foreach ($ownerIds as $id) {
                        foreach ($map->related($ownerType, $id, $name) ?? [] as $model) {
                            $related[$this->id($target, $model)] = $model;
                        }
                    }
                    // Empty child tree still needs the included representation's ordinary linkage/profile requirements.
                    $queue[] = [$target->type, array_values($related), $edge->includeChildren];
                }
            }
        }
        return $map;
    }

    /** @param list<string> $ids
     * @return array{QueryBuilder, string}
     */
    private function edgeQuery(EntityManagerInterface $em, ResourceMetadata $owner, string $path, array $ids): array
    {
        $query = $em->createQueryBuilder()->from($owner->dataClass, 'source');
        $alias = 'source';
        foreach (explode('.', $path) as $index => $segment) {
            $next = 'related_' . $index;
            $query->leftJoin($alias . '.' . $segment, $next);
            $alias = $next;
        }
        $ownerField = $em->getClassMetadata($owner->dataClass)->getSingleIdentifierFieldName();
        $query->where('source.' . $ownerField . ' IN (:owners)');
        IdentifierParameters::bind($query, $em, $owner->dataClass, 'owners', $ids);
        return [$query, $alias];
    }

    /** @param list<string> $ids */
    private function loadModels(EntityManagerInterface $em, ResourceMetadata $target, array $ids, Criteria $criteria, RelationshipReadMap $map, ?ProfileContext $context): void
    {
        foreach (array_chunk($ids, 256) as $chunk) {
            $field = $em->getClassMetadata($target->dataClass)->getSingleIdentifierFieldName();
            $query = $em->createQueryBuilder()->from($target->dataClass, 'e')->where('e.' . $field . ' IN (:targets)');
            IdentifierParameters::bind($query, $em, $target->dataClass, 'targets', $chunk);
            $definition = $target->getDefinition($context?->forType($target->type));
            if ($definition->readProjection === ReadProjection::DTO) {
                \AlexFigures\Symfony\Bridge\Doctrine\Query\DoctrineReadProjection::apply($query, $definition);
                $rows = $query->getQuery()->getArrayResult();
            } else {
                $query->select('e');
                /** @var list<object> $rows */
                $rows = $query->getQuery()->getResult();
            }
            foreach ($rows as $row) {
                $model = $this->mapper->toView($row, $definition, $criteria);
                $map->remember($target->type, $this->id($target, $model), $model);
            }
        }
    }

    /** @return list<array<string, int|string|\Stringable|null>> */
    private function scalarRows(QueryBuilder $query): array
    {
        /** @var list<array<string, int|string|\Stringable|null>> $rows */
        $rows = $query->getQuery()->getScalarResult();
        return $rows;
    }

    private function id(ResourceMetadata $metadata, object $model): string
    {
        $id = $this->accessor->getValue($model, $metadata->idPropertyPath ?? 'id');
        if (!is_scalar($id) && !$id instanceof \Stringable) {
            throw new \LogicException('Resource identifier must be scalar or Stringable.');
        }
        return (string) $id;
    }
}
