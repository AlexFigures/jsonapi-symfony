<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Bridge\Doctrine\Relationship;

use AlexFigures\JsonApi\Bridge\Doctrine\Flush\FlushManager;
use AlexFigures\JsonApi\Contract\Data\RelationshipReader;
use AlexFigures\JsonApi\Contract\Data\RelationshipUpdater;
use AlexFigures\JsonApi\Contract\Data\ResourceIdentifier;
use AlexFigures\JsonApi\Contract\Data\Slice;
use AlexFigures\JsonApi\Contract\Data\SliceIds;
use AlexFigures\JsonApi\Http\Exception\NotFoundException;
use AlexFigures\JsonApi\Query\Criteria;
use AlexFigures\JsonApi\Query\Pagination;
use AlexFigures\JsonApi\Resource\Metadata\RelationshipMetadata;
use AlexFigures\JsonApi\Resource\Metadata\ResourceMetadata;
use AlexFigures\JsonApi\Resource\Registry\ResourceRegistryInterface;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use InvalidArgumentException;
use RuntimeException;
use Stringable;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Generic Doctrine implementation for reading and updating relationships.
 *
 * Automatically determines relationship type through Doctrine metadata
 * and performs corresponding operations.
 *
 * Supports:
 * - OneToOne
 * - ManyToOne
 * - OneToMany
 * - ManyToMany
 * @internal
 */
final readonly class GenericDoctrineRelationshipHandler implements RelationshipReader, RelationshipUpdater
{
    public function __construct(
        private ManagerRegistry $managerRegistry,
        private ResourceRegistryInterface $registry,
        private PropertyAccessorInterface $accessor,
        private FlushManager $flushManager,
        private ?\AlexFigures\JsonApi\Contract\Data\ResourceRepository $repository = null,
        private ?\AlexFigures\JsonApi\Http\Request\QueryParser $queryParser = null,
        private ?\Symfony\Component\HttpFoundation\RequestStack $requests = null,
        private string $unplannedReadPolicy = 'legacy',
    ) {
    }

    // ==================== RelationshipReader ====================

    public function getToOneId(string|object $type, string $idOrRel, ?string $rel = null): ?string
    {
        if (is_string($type) && $this->repository !== null) {
            $visibleOwner = $this->repository->findOne($type, $idOrRel, $this->graphCriteria($type));
            if ($visibleOwner === null) {
                throw new NotFoundException('Relationship owner not found.');
            }
        }
        [$resource, $metadata, $relationship] = $this->resolveResourceContext($type, $idOrRel, $rel);
        if ($this->repository !== null) {
            $query = (new DoctrineRelationshipQueryFactory($this->managerRegistry, $this->registry))->select($metadata->type, $relationship, [$this->extractId($resource)], new Criteria(new Pagination(1, 1)));
            if ($query !== null) {
                [$targetMetadata, $selected] = $query;
                $scope = $this->graphCriteria($targetMetadata->type);
                $selected->customConditions = array_merge($scope->customConditions, $selected->customConditions);
                $selected->identifiersOnly = true;
                $slice = $this->repository->findCollection($targetMetadata->type, $selected);
                $model = $slice->items[0] ?? null;
                return $model === null ? null : ($model instanceof ResourceIdentifier ? $model->id : $this->viewIdentifier($targetMetadata, $model));
            }
        }
        if ($this->unplannedReadPolicy === 'reject') {
            throw new \AlexFigures\JsonApi\Http\Exception\BadRequestException('Unplanned relationship read is disabled. Register a bounded relationship reader.');
        }
        $propertyPath = $this->resolveRelationshipProperty($metadata, $relationship);

        $related = $this->accessor->getValue($resource, $propertyPath);

        if ($related === null) {
            return null;
        }

        if (!is_object($related)) {
            throw new RuntimeException(sprintf('Relationship "%s" on resource "%s" must resolve to an object or null.', $relationship, $metadata->type));
        }

        if ($this->repository !== null) {
            $targetType = $metadata->relationships[$relationship]->targetType;
            if ($targetType === null) {
                throw new \LogicException('Computed relationship requires a target resource type.');
            }
            $slice = $this->fallbackCollection($targetType, [$this->extractId($related)], $this->graphCriteria($targetType), true);
            $model = $slice->items[0] ?? null;
            return $model === null ? null : ($model instanceof ResourceIdentifier ? $model->id : $this->viewIdentifier($this->registry->getByType($targetType), $model));
        }

        return $this->extractId($related);
    }

    public function getToManyIds(string|object $type, string $idOrRel, ?string $rel = null, ?Pagination $pagination = null): SliceIds
    {
        if (is_string($type) && $this->repository !== null) {
            $visibleOwner = $this->repository->findOne($type, $idOrRel, $this->graphCriteria($type));
            if ($visibleOwner === null) {
                throw new NotFoundException('Relationship owner not found.');
            }
        }
        [$resource, $metadata, $relationship] = $this->resolveResourceContext($type, $idOrRel, $rel);
        if ($this->repository !== null) {
            $query = (new DoctrineRelationshipQueryFactory($this->managerRegistry, $this->registry))->select($metadata->type, $relationship, [$this->extractId($resource)], new Criteria($pagination ?? new Pagination(1, 10)));
            if ($query !== null) {
                [$targetMetadata, $selected] = $query;
                $scope = $this->graphCriteria($targetMetadata->type);
                $selected->customConditions = array_merge($scope->customConditions, $selected->customConditions);
                $selected->identifiersOnly = true;
                $slice = $this->repository->findCollection($targetMetadata->type, $selected);
                return new SliceIds(array_map(fn (object $model): string => $model instanceof ResourceIdentifier ? $model->id : $this->viewIdentifier($targetMetadata, $model), $slice->items), $slice->pageNumber, $slice->pageSize, $slice->totalItems);
            }
        }
        if ($this->unplannedReadPolicy === 'reject') {
            throw new \AlexFigures\JsonApi\Http\Exception\BadRequestException('Unplanned relationship read is disabled. Register a bounded relationship reader.');
        }
        $propertyPath = $this->resolveRelationshipProperty($metadata, $relationship);
        $pagination ??= new Pagination(1, 10);

        $related = $this->accessor->getValue($resource, $propertyPath);

        if ($related === null) {
            return new SliceIds([], 1, $pagination->size, 0);
        }

        if ($related instanceof Collection) {
            $related = $related->toArray();
        } elseif (!is_array($related)) {
            return new SliceIds([], 1, $pagination->size, 0);
        }

        $objects = $this->ensureObjectList($related, 'to-many relationship items');

        $ids = [];
        foreach ($objects as $item) {
            $ids[] = $this->extractId($item);
        }

        if ($this->repository !== null) {
            $target = $metadata->relationships[$relationship]->targetType;
            if ($target === null) {
                throw new \LogicException('Computed relationship requires a target resource type.');
            }
            $selected = $this->graphCriteria($target);
            $selected->pagination = $pagination;
            $slice = $this->fallbackCollection($target, $ids, $selected, true);
            $targetMetadata = $this->registry->getByType($target);
            return new SliceIds(array_map(fn (object $model): string => $model instanceof ResourceIdentifier ? $model->id : $this->viewIdentifier($targetMetadata, $model), $slice->items), $slice->pageNumber, $slice->pageSize, $slice->totalItems);
        }

        $total = count($ids);

        // Apply pagination
        $offset = ($pagination->number - 1) * $pagination->size;
        $paginatedIds = array_slice($ids, $offset, $pagination->size);

        return new SliceIds($paginatedIds, $pagination->number, $pagination->size, $total);
    }

    public function getRelatedResource(string|object $type, string $idOrRel, ?string $rel = null): ?object
    {
        if (is_string($type) && $this->repository !== null) {
            $visibleOwner = $this->repository->findOne($type, $idOrRel, $this->graphCriteria($type));
            if ($visibleOwner === null) {
                throw new NotFoundException('Relationship owner not found.');
            }
        }
        [$resource, $metadata, $relationship] = $this->resolveResourceContext($type, $idOrRel, $rel);
        if ($this->repository !== null) {
            $query = (new DoctrineRelationshipQueryFactory($this->managerRegistry, $this->registry))->select($metadata->type, $relationship, [$this->extractId($resource)], new Criteria(new Pagination(1, 1)));
            if ($query !== null) {
                [$targetMetadata, $selected] = $query;
                $scope = $this->graphCriteria($targetMetadata->type);
                $selected->customConditions = array_merge($scope->customConditions, $selected->customConditions);
                $slice = $this->repository->findCollection($targetMetadata->type, $selected);
                return $slice->items[0] ?? null;
            }
        }
        if ($this->unplannedReadPolicy === 'reject') {
            throw new \AlexFigures\JsonApi\Http\Exception\BadRequestException('Unplanned relationship read is disabled. Register a bounded relationship reader.');
        }
        $propertyPath = $this->resolveRelationshipProperty($metadata, $relationship);

        $value = $this->accessor->getValue($resource, $propertyPath);

        if ($value === null) {
            return null;
        }

        if (!is_object($value)) {
            throw new RuntimeException(sprintf('Relationship "%s" on resource "%s" must resolve to an object or null.', $relationship, $metadata->type));
        }

        if ($this->repository !== null) {
            $targetType = $metadata->relationships[$relationship]->targetType;
            if ($targetType === null) {
                throw new \LogicException('Computed relationship requires a target resource type.');
            }
            return $this->fallbackCollection($targetType, [$this->extractId($value)], $this->graphCriteria($targetType))->items[0] ?? null;
        }

        return $value;
    }

    public function getRelatedCollection(string|object $type, string $idOrRel, ?string $rel = null, ?Criteria $criteria = null): Slice
    {
        if (is_string($type) && $this->repository !== null) {
            $visibleOwner = $this->repository->findOne($type, $idOrRel, $this->graphCriteria($type));
            if ($visibleOwner === null) {
                throw new NotFoundException('Relationship owner not found.');
            }
        }
        [$resource, $metadata, $relationship] = $this->resolveResourceContext($type, $idOrRel, $rel);
        if ($this->repository !== null) {
            $query = (new DoctrineRelationshipQueryFactory($this->managerRegistry, $this->registry))->select($metadata->type, $relationship, [$this->extractId($resource)], $criteria ?? new Criteria());
            if ($query !== null) {
                [$targetMetadata, $selected] = $query;

                $slice = $this->repository->findCollection($targetMetadata->type, $selected);
                return $slice;
            }
        }
        if ($this->unplannedReadPolicy === 'reject') {
            throw new \AlexFigures\JsonApi\Http\Exception\BadRequestException('Unplanned relationship read is disabled. Register a bounded relationship reader.');
        }
        $propertyPath = $this->resolveRelationshipProperty($metadata, $relationship);
        $criteria ??= new Criteria();

        $related = $this->accessor->getValue($resource, $propertyPath);

        if ($related === null) {
            return new Slice([], 1, $criteria->pagination->size, 0);
        }

        $items = [];
        if ($related instanceof Collection) {
            $items = $related->toArray();
        } elseif (is_array($related)) {
            $items = $related;
        }

        $objects = $this->ensureObjectList($items, 'related collection items');
        if ($this->repository !== null) {
            $targetType = $metadata->relationships[$relationship]->targetType;
            if ($targetType !== null) {
                $ids = array_map($this->extractId(...), $objects);
                $targetMetadata = $this->registry->getByType($targetType);
                $targetEm = $this->getEntityManagerFor($targetMetadata->dataClass);
                $identifier = $targetEm->getClassMetadata($targetMetadata->dataClass)->getSingleIdentifierFieldName();
                $selected = clone $criteria;
                $selected->customConditions[] = static function (\Doctrine\ORM\QueryBuilder $qb) use ($ids, $identifier, $targetMetadata, $targetEm): void {
                    if ($ids === []) {
                        $qb->andWhere('1 = 0');
                        return;
                    }
                    $qb->andWhere($qb->getRootAliases()[0] . '.' . $identifier . ' IN (:relationshipIds)');
                    \AlexFigures\JsonApi\Bridge\Doctrine\Identifier\IdentifierParameters::bind($qb, $targetEm, $targetMetadata->dataClass, 'relationshipIds', $ids);
                };
                return $this->repository->findCollection($targetType, $selected);
            }
        }
        $total = count($objects);

        // Apply pagination
        $offset = ($criteria->pagination->number - 1) * $criteria->pagination->size;
        $paginatedItems = array_slice($objects, $offset, $criteria->pagination->size);

        // Note: Filtering and sorting on in-memory collections is not optimal
        // For production use, consider using QueryBuilder for relationship queries
        return new Slice($paginatedItems, $criteria->pagination->number, $criteria->pagination->size, $total);
    }

    // ==================== RelationshipUpdater ====================

    public function replaceToOne(string|object $type, string $idOrRel, mixed $relOrTarget, ?ResourceIdentifier $target = null): void
    {
        [$resource, $metadata, $relationship, $payload] = $this->resolveToOneUpdateArguments($type, $idOrRel, $relOrTarget, $target);
        $propertyPath = $this->resolveRelationshipProperty($metadata, $relationship);
        $relationshipMetadata = $this->requireRelationshipMetadata($metadata, $relationship);
        $targetClass = $this->determineTargetClass($relationshipMetadata, $this->getClassMetadata($resource), $propertyPath);

        $normalizedTargetId = $this->normalizeTargetId($relationshipMetadata, $payload);
        $this->beforeRelationshipMutation($resource, $metadata, $relationshipMetadata, 'onBeforeRelReplaceToOne', $normalizedTargetId === null ? [] : [$normalizedTargetId]);

        if ($normalizedTargetId === null) {
            $em = $this->getEntityManagerFor($resource::class);
            if (!RelationshipNullability::allowsNull($em, $resource, $propertyPath, $relationshipMetadata->nullable)) {
                throw new \AlexFigures\JsonApi\Http\Exception\ValidationException([new \AlexFigures\JsonApi\Http\Error\ErrorObject(null, null, '422', 'validation-error', 'Validation Error', 'Relationship cannot be null.', new \AlexFigures\JsonApi\Http\Error\ErrorSource(pointer: '/data'))]);
            }
            $this->accessor->setValue($resource, $propertyPath, null);
        } else {
            $relatedEntity = $this->findRelatedEntity($targetClass, $normalizedTargetId);
            $this->accessor->setValue($resource, $propertyPath, $relatedEntity);
        }

        $this->flushManager->scheduleFlush($resource::class);
    }

    /**
     * @param list<ResourceIdentifier|string> $targets
     */
    public function replaceToMany(string|object $type, string $idOrRel, mixed $relOrTargets, array $targets = []): void
    {
        /** @var list<ResourceIdentifier|string> $targetList */
        [$resource, $metadata, $relationship, $targetList] = $this->resolveToManyUpdateArguments($type, $idOrRel, $relOrTargets, $targets);
        $propertyPath = $this->resolveRelationshipProperty($metadata, $relationship);
        $relationshipMetadata = $this->requireRelationshipMetadata($metadata, $relationship);
        $targetClass = $this->determineTargetClass($relationshipMetadata, $this->getClassMetadata($resource), $propertyPath);

        $normalizedIds = $this->normalizeTargetIds($relationshipMetadata, $targetList);
        $this->beforeRelationshipMutation($resource, $metadata, $relationshipMetadata, 'onBeforeRelReplaceToMany', $normalizedIds);

        $collection = $this->accessor->getValue($resource, $propertyPath);

        if (!$collection instanceof Collection) {
            throw new \RuntimeException(sprintf('Property "%s" is not a Doctrine Collection', $propertyPath));
        }

        $collection->clear();

        foreach ($normalizedIds as $targetId) {
            $relatedEntity = $this->findRelatedEntity($targetClass, $targetId);
            $collection->add($relatedEntity);
        }

        $this->flushManager->scheduleFlush($resource::class);
    }

    /**
     * @param list<ResourceIdentifier|string> $targets
     */
    public function addToMany(string|object $type, string $idOrRel, mixed $relOrTargets, array $targets = []): void
    {
        /** @var list<ResourceIdentifier|string> $targetList */
        [$resource, $metadata, $relationship, $targetList] = $this->resolveToManyUpdateArguments($type, $idOrRel, $relOrTargets, $targets);
        $propertyPath = $this->resolveRelationshipProperty($metadata, $relationship);
        $relationshipMetadata = $this->requireRelationshipMetadata($metadata, $relationship);
        $targetClass = $this->determineTargetClass($relationshipMetadata, $this->getClassMetadata($resource), $propertyPath);

        $normalizedIds = $this->normalizeTargetIds($relationshipMetadata, $targetList);
        $this->beforeRelationshipMutation($resource, $metadata, $relationshipMetadata, 'onBeforeRelAddToMany', $normalizedIds);

        $collection = $this->accessor->getValue($resource, $propertyPath);

        if (!$collection instanceof Collection) {
            throw new \RuntimeException(sprintf('Property "%s" is not a Doctrine Collection', $propertyPath));
        }

        foreach ($normalizedIds as $targetId) {
            $relatedEntity = $this->findRelatedEntity($targetClass, $targetId);

            if (!$collection->contains($relatedEntity)) {
                $collection->add($relatedEntity);
            }
        }

        $this->flushManager->scheduleFlush($resource::class);
    }

    /**
     * @param list<ResourceIdentifier|string> $targets
     */
    public function removeFromToMany(string|object $type, string $idOrRel, mixed $relOrTargets, array $targets = []): void
    {
        /** @var list<ResourceIdentifier|string> $targetList */
        [$resource, $metadata, $relationship, $targetList] = $this->resolveToManyUpdateArguments($type, $idOrRel, $relOrTargets, $targets);
        $propertyPath = $this->resolveRelationshipProperty($metadata, $relationship);
        $relationshipMetadata = $this->requireRelationshipMetadata($metadata, $relationship);
        $targetClass = $this->determineTargetClass($relationshipMetadata, $this->getClassMetadata($resource), $propertyPath);

        $normalizedIds = $this->normalizeTargetIds($relationshipMetadata, $targetList);
        $this->beforeRelationshipMutation($resource, $metadata, $relationshipMetadata, 'onBeforeRelRemoveFromToMany', $normalizedIds);

        $collection = $this->accessor->getValue($resource, $propertyPath);

        if (!$collection instanceof Collection) {
            throw new \RuntimeException(sprintf('Property "%s" is not a Doctrine Collection', $propertyPath));
        }

        foreach ($normalizedIds as $targetId) {
            $relatedEntity = $this->findRelatedEntity($targetClass, $targetId);
            $collection->removeElement($relatedEntity);
        }

        $this->flushManager->scheduleFlush($resource::class);
    }

    /** @param list<string> $ids */
    private function beforeRelationshipMutation(object $resource, ResourceMetadata $metadata, RelationshipMetadata $relationship, string $method, array $ids): void
    {
        $request = $this->requests?->getCurrentRequest();
        $context = $request === null ? null : \AlexFigures\JsonApi\Profile\ProfileContext::fromRequest($request)?->forType($metadata->type);
        if ($context === null || $context->relationshipHooks() === []) {
            return;
        }
        $type = $relationship->targetType ?? ($relationship->targetClass === null ? null : $this->registry->getByClass($relationship->targetClass)?->type);
        if ($type === null) {
            throw new \LogicException('Relationship hooks require a resolved target resource type.');
        }
        $targets = array_map(static fn (string $id): ResourceIdentifier => new ResourceIdentifier($type, $id), $ids);
        $ownerId = $this->extractId($resource);
        foreach ($context->relationshipHooks() as $hook) {
            if ($method === 'onBeforeRelReplaceToOne') {
                $hook->onBeforeRelReplaceToOne($context, $metadata->type, $ownerId, $relationship->name, $targets[0] ?? null);
            } else {
                $hook->{$method}($context, $metadata->type, $ownerId, $relationship->name, $targets);
            }
        }
    }

    // ==================== Private helpers ====================

    /** @param list<string> $ids */
    private function fallbackCollection(string $targetType, array $ids, Criteria $criteria, bool $identifiers = false): Slice
    {
        if ($this->repository === null) {
            throw new \LogicException('Scoped fallback requires a repository.');
        }
        $metadata = $this->registry->getByType($targetType);
        $em = $this->getEntityManagerFor($metadata->dataClass);
        $field = $em->getClassMetadata($metadata->dataClass)->getSingleIdentifierFieldName();
        $selected = clone $criteria;
        $selected->identifiersOnly = $identifiers;
        $selected->customConditions[] = static function (\Doctrine\ORM\QueryBuilder $query) use ($ids, $field, $metadata, $em): void {
            if ($ids === []) {
                $query->andWhere('1 = 0');
                return;
            }
            $query->andWhere($query->getRootAliases()[0] . '.' . $field . ' IN (:fallback_ids)');
            \AlexFigures\JsonApi\Bridge\Doctrine\Identifier\IdentifierParameters::bind($query, $em, $metadata->dataClass, 'fallback_ids', $ids);
        };
        return $this->repository->findCollection($targetType, $selected);
    }

    private function graphCriteria(string $type): Criteria
    {
        $request = $this->requests?->getCurrentRequest();
        return $request !== null && $this->queryParser !== null ? $this->queryParser->parseGraph($type, $request) : new Criteria();
    }

    private function findResource(string $type, string $id): object
    {
        $metadata = $this->registry->getByType($type);
        $entityClass = $metadata->dataClass;

        $em = $this->getEntityManagerFor($entityClass);
        $entity = $em->find($entityClass, \AlexFigures\JsonApi\Bridge\Doctrine\Identifier\IdentifierConverter::convert($em, $entityClass, $id));

        if ($entity === null) {
            throw new NotFoundException(
                sprintf('Resource "%s" with id "%s" not found', $type, $id)
            );
        }

        return $entity;
    }

    private function viewIdentifier(ResourceMetadata $metadata, object $model): string
    {
        $id = $this->accessor->getValue($model, $metadata->idPropertyPath ?? 'id');
        if (!is_scalar($id) && !$id instanceof Stringable) {
            throw new \LogicException('Resource identifier must be scalar or Stringable.');
        }
        return (string) $id;
    }

    private function extractId(object $entity): string
    {
        $metadata = $this->getEntityManagerFor($entity::class)->getClassMetadata($entity::class);
        $idFields = $metadata->getIdentifierFieldNames();

        if (count($idFields) !== 1) {
            throw new \RuntimeException(sprintf(
                'Composite keys are not supported. Entity "%s" has %d identifier fields.',
                $entity::class,
                count($idFields)
            ));
        }

        $idField = $idFields[0];
        $id = $this->accessor->getValue($entity, $idField);

        if (!is_scalar($id) && !$id instanceof Stringable) {
            throw new RuntimeException(sprintf('Identifier for entity "%s" must be scalar or stringable.', $entity::class));
        }

        return (string) $id;
    }

    /**
     * @return ClassMetadata<object>
     */
    private function getClassMetadata(object $entity): ClassMetadata
    {
        return $this->getEntityManagerFor($entity::class)->getClassMetadata($entity::class);
    }

    /**
     * @param class-string $entityClass
     */
    private function findRelatedEntity(string $entityClass, string $id): object
    {
        $em = $this->getEntityManagerFor($entityClass);
        $entity = $em->find($entityClass, \AlexFigures\JsonApi\Bridge\Doctrine\Identifier\IdentifierConverter::convert($em, $entityClass, $id));

        if ($entity === null) {
            throw new NotFoundException(
                sprintf('Related entity "%s" with id "%s" not found', $entityClass, $id)
            );
        }

        return $entity;
    }

    /**
     * @return array{object, ResourceMetadata, string}
     */
    private function resolveResourceContext(string|object $typeOrResource, string $idOrRel, ?string $relationship): array
    {
        if (is_object($typeOrResource)) {
            $metadata = $this->requireMetadataForObject($typeOrResource);

            if ($idOrRel === '') {
                throw new InvalidArgumentException('Relationship name must not be empty.');
            }

            return [$typeOrResource, $metadata, $idOrRel];
        }

        if ($relationship === null || $relationship === '') {
            throw new InvalidArgumentException('Relationship name must be provided.');
        }

        $resource = $this->findResource($typeOrResource, $idOrRel);
        $metadata = $this->registry->getByType($typeOrResource);

        return [$resource, $metadata, $relationship];
    }

    /**
     * @param ClassMetadata<object> $metadata
     *
     * @return class-string
     */
    private function resolveTargetEntity(ClassMetadata $metadata, string $relationship): string
    {
        if (!$metadata->hasAssociation($relationship)) {
            throw new \RuntimeException(sprintf(
                'Property "%s" is not an association in entity "%s"',
                $relationship,
                $metadata->getName()
            ));
        }

        return $this->assertEntityClass(
            $metadata->getAssociationTargetClass($relationship),
            sprintf('association "%s" on "%s"', $relationship, $metadata->getName()),
        );
    }

    /**
     * @param ClassMetadata<object> $entityMetadata
     *
     * @return class-string
     */
    private function determineTargetClass(RelationshipMetadata $relationship, ClassMetadata $entityMetadata, string $propertyPath): string
    {
        // If targetClass is specified and it's not a Collection interface, use it
        if ($relationship->targetClass !== null && !is_a($relationship->targetClass, Collection::class, true)) {
            return $this->assertEntityClass(
                $relationship->targetClass,
                sprintf('targetClass for relationship "%s"', $relationship->name),
            );
        }

        // Try to resolve from targetType in registry
        if ($relationship->targetType !== null && $this->registry->hasType($relationship->targetType)) {
            return $this->registry->getByType($relationship->targetType)->dataClass;
        }

        // Fall back to Doctrine metadata
        return $this->resolveTargetEntity($entityMetadata, $propertyPath);
    }

    private function requireMetadataForObject(object $resource): ResourceMetadata
    {
        $metadata = $this->registry->getByClass($resource::class);

        if ($metadata === null) {
            throw new InvalidArgumentException(sprintf('No resource metadata registered for class "%s".', $resource::class));
        }

        return $metadata;
    }

    private function resolveRelationshipProperty(ResourceMetadata $metadata, string $relationship): string
    {
        $relationshipMetadata = $this->requireRelationshipMetadata($metadata, $relationship);

        return $relationshipMetadata->propertyPath ?? $relationshipMetadata->name;
    }

    private function requireRelationshipMetadata(ResourceMetadata $metadata, string $relationship): RelationshipMetadata
    {
        if (!isset($metadata->relationships[$relationship])) {
            throw new InvalidArgumentException(sprintf('Unknown relationship "%s" for resource type "%s".', $relationship, $metadata->type));
        }

        return $metadata->relationships[$relationship];
    }

    /**
     * @return array{object, ResourceMetadata, string, ResourceIdentifier|string|null}
     */
    private function resolveToOneUpdateArguments(string|object $typeOrResource, string $idOrRel, mixed $relOrTarget, ?ResourceIdentifier $target): array
    {
        if (is_object($typeOrResource)) {
            if ($relOrTarget !== null && !$relOrTarget instanceof ResourceIdentifier && !is_string($relOrTarget)) {
                throw new InvalidArgumentException('Target must be a string id, ResourceIdentifier instance, or null.');
            }

            $metadata = $this->requireMetadataForObject($typeOrResource);

            return [$typeOrResource, $metadata, $idOrRel, $relOrTarget];
        }

        if (!is_string($relOrTarget)) {
            throw new InvalidArgumentException('Relationship name must be provided as a string.');
        }

        $resource = $this->findResource($typeOrResource, $idOrRel);
        $metadata = $this->registry->getByType($typeOrResource);

        return [$resource, $metadata, $relOrTarget, $target];
    }

    /**
     * @param list<ResourceIdentifier|string> $targets
     *
     * @return array{object, ResourceMetadata, string, list<ResourceIdentifier|string>}
     */
    private function resolveToManyUpdateArguments(string|object $typeOrResource, string $idOrRel, mixed $relOrTargets, array $targets): array
    {
        if (is_object($typeOrResource)) {
            if (!is_array($relOrTargets)) {
                throw new InvalidArgumentException('Targets must be provided as an array when passing a resource instance.');
            }

            $metadata = $this->requireMetadataForObject($typeOrResource);

            return [$typeOrResource, $metadata, $idOrRel, $this->normalizeTargetPayload($relOrTargets)];
        }

        if (!is_string($relOrTargets)) {
            throw new InvalidArgumentException('Relationship name must be provided as a string.');
        }

        $resource = $this->findResource($typeOrResource, $idOrRel);
        $metadata = $this->registry->getByType($typeOrResource);

        return [$resource, $metadata, $relOrTargets, $this->normalizeTargetPayload($targets)];
    }

    /**
     * @param RelationshipMetadata           $relationship
     * @param ResourceIdentifier|string|null $target
     */
    private function normalizeTargetId(RelationshipMetadata $relationship, ResourceIdentifier|string|null $target): ?string
    {
        if ($target === null) {
            return null;
        }

        if ($target instanceof ResourceIdentifier) {
            if ($relationship->targetType !== null && $target->type !== $relationship->targetType) {
                throw new InvalidArgumentException(sprintf(
                    'Target type "%s" does not match expected type "%s" for relationship "%s".',
                    $target->type,
                    $relationship->targetType,
                    $relationship->name
                ));
            }

            return $target->id;
        }

        return $target;
    }

    /**
     * @param RelationshipMetadata            $relationship
     * @param list<ResourceIdentifier|string> $targets
     *
     * @return list<string>
     */
    private function normalizeTargetIds(RelationshipMetadata $relationship, array $targets): array
    {
        $ids = [];
        foreach ($targets as $target) {
            $normalized = $this->normalizeTargetId($relationship, $target);

            if ($normalized === null) {
                throw new InvalidArgumentException(sprintf('Null target is not allowed for to-many relationship "%s".', $relationship->name));
            }

            $ids[] = $normalized;
        }

        return $ids;
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

    /**
     * @param array<int|string, mixed> $items
     *
     * @return list<object>
     */
    private function ensureObjectList(array $items, string $context): array
    {
        $objects = [];
        foreach ($items as $item) {
            if (!is_object($item)) {
                throw new RuntimeException(sprintf('Expected list of objects for %s, got %s.', $context, get_debug_type($item)));
            }

            $objects[] = $item;
        }

        return $objects;
    }

    /**
     * @param array<int|string, mixed> $targets
     *
     * @return list<ResourceIdentifier|string>
     */
    private function normalizeTargetPayload(array $targets): array
    {
        $normalized = [];
        foreach ($targets as $target) {
            if (!$target instanceof ResourceIdentifier && !is_string($target)) {
                throw new InvalidArgumentException(sprintf('Targets must be strings or ResourceIdentifier instances, %s given.', get_debug_type($target)));
            }

            $normalized[] = $target;
        }

        return $normalized;
    }

    /**
     * @return class-string
     */
    private function assertEntityClass(string $candidate, string $context): string
    {
        if (class_exists($candidate) || interface_exists($candidate)) {
            return $candidate;
        }

        throw new RuntimeException(sprintf('Configured entity class "%s" for %s does not exist.', $candidate, $context));
    }
}
