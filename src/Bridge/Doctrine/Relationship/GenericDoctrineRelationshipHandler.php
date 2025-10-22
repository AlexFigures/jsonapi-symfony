<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Bridge\Doctrine\Relationship;

use AlexFigures\Symfony\Bridge\Doctrine\Flush\FlushManager;
use AlexFigures\Symfony\Contract\Data\RelationshipReader;
use AlexFigures\Symfony\Contract\Data\RelationshipUpdater;
use AlexFigures\Symfony\Contract\Data\ResourceIdentifier;
use AlexFigures\Symfony\Contract\Data\Slice;
use AlexFigures\Symfony\Contract\Data\SliceIds;
use AlexFigures\Symfony\Http\Exception\NotFoundException;
use AlexFigures\Symfony\Query\Criteria;
use AlexFigures\Symfony\Query\Pagination;
use AlexFigures\Symfony\Resource\Metadata\RelationshipMetadata;
use AlexFigures\Symfony\Resource\Metadata\ResourceMetadata;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
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
 */
final class GenericDoctrineRelationshipHandler implements RelationshipReader, RelationshipUpdater
{
    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
        private readonly ResourceRegistryInterface $registry,
        private readonly PropertyAccessorInterface $accessor,
        private readonly FlushManager $flushManager,
    ) {
    }

    // ==================== RelationshipReader ====================

    public function getToOneId(string|object $type, string $idOrRel, ?string $rel = null): ?string
    {
        [$resource, $metadata, $relationship] = $this->resolveResourceContext($type, $idOrRel, $rel);
        $propertyPath = $this->resolveRelationshipProperty($metadata, $relationship);

        $related = $this->accessor->getValue($resource, $propertyPath);

        if ($related === null) {
            return null;
        }

        if (!is_object($related)) {
            throw new RuntimeException(sprintf('Relationship "%s" on resource "%s" must resolve to an object or null.', $relationship, $metadata->type));
        }

        return $this->extractId($related);
    }

    public function getToManyIds(string|object $type, string $idOrRel, ?string $rel = null, ?Pagination $pagination = null): SliceIds
    {
        [$resource, $metadata, $relationship] = $this->resolveResourceContext($type, $idOrRel, $rel);
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

        $total = count($ids);

        // Apply pagination
        $offset = ($pagination->number - 1) * $pagination->size;
        $paginatedIds = array_slice($ids, $offset, $pagination->size);

        return new SliceIds($paginatedIds, $pagination->number, $pagination->size, $total);
    }

    public function getRelatedResource(string|object $type, string $idOrRel, ?string $rel = null): ?object
    {
        [$resource, $metadata, $relationship] = $this->resolveResourceContext($type, $idOrRel, $rel);
        $propertyPath = $this->resolveRelationshipProperty($metadata, $relationship);

        $value = $this->accessor->getValue($resource, $propertyPath);

        if ($value === null) {
            return null;
        }

        if (!is_object($value)) {
            throw new RuntimeException(sprintf('Relationship "%s" on resource "%s" must resolve to an object or null.', $relationship, $metadata->type));
        }

        return $value;
    }

    public function getRelatedCollection(string|object $type, string $idOrRel, ?string $rel = null, ?Criteria $criteria = null): Slice
    {
        [$resource, $metadata, $relationship] = $this->resolveResourceContext($type, $idOrRel, $rel);
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

        if ($normalizedTargetId === null) {
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

        $collection = $this->accessor->getValue($resource, $propertyPath);

        if (!$collection instanceof Collection) {
            throw new \RuntimeException(sprintf('Property "%s" is not a Doctrine Collection', $propertyPath));
        }

        $collection->clear();

        foreach ($this->normalizeTargetIds($relationshipMetadata, $targetList) as $targetId) {
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

        $collection = $this->accessor->getValue($resource, $propertyPath);

        if (!$collection instanceof Collection) {
            throw new \RuntimeException(sprintf('Property "%s" is not a Doctrine Collection', $propertyPath));
        }

        foreach ($this->normalizeTargetIds($relationshipMetadata, $targetList) as $targetId) {
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

        $collection = $this->accessor->getValue($resource, $propertyPath);

        if (!$collection instanceof Collection) {
            throw new \RuntimeException(sprintf('Property "%s" is not a Doctrine Collection', $propertyPath));
        }

        foreach ($this->normalizeTargetIds($relationshipMetadata, $targetList) as $targetId) {
            $relatedEntity = $this->findRelatedEntity($targetClass, $targetId);
            $collection->removeElement($relatedEntity);
        }

        $this->flushManager->scheduleFlush($resource::class);
    }

    // ==================== Private helpers ====================

    private function findResource(string $type, string $id): object
    {
        $metadata = $this->registry->getByType($type);
        $entityClass = $metadata->dataClass;

        $em = $this->getEntityManagerFor($entityClass);
        $entity = $em->find($entityClass, $id);

        if ($entity === null) {
            throw new NotFoundException(
                sprintf('Resource "%s" with id "%s" not found', $type, $id)
            );
        }

        return $entity;
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
        $entity = $em->find($entityClass, $id);

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
