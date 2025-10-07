<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Bridge\Doctrine\Relationship;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use InvalidArgumentException;
use JsonApi\Symfony\Contract\Data\RelationshipReader;
use JsonApi\Symfony\Contract\Data\RelationshipUpdater;
use JsonApi\Symfony\Contract\Data\ResourceIdentifier;
use JsonApi\Symfony\Contract\Data\Slice;
use JsonApi\Symfony\Contract\Data\SliceIds;
use JsonApi\Symfony\Http\Exception\NotFoundException;
use JsonApi\Symfony\Query\Criteria;
use JsonApi\Symfony\Query\Pagination;
use JsonApi\Symfony\Resource\Metadata\RelationshipMetadata;
use JsonApi\Symfony\Resource\Metadata\ResourceMetadata;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Generic Doctrine реализация для чтения и обновления связей.
 *
 * Автоматически определяет тип связи через Doctrine metadata
 * и выполняет соответствующие операции.
 *
 * Поддерживает:
 * - OneToOne
 * - ManyToOne
 * - OneToMany
 * - ManyToMany
 */
final class GenericDoctrineRelationshipHandler implements RelationshipReader, RelationshipUpdater
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ResourceRegistryInterface $registry,
        private readonly PropertyAccessorInterface $accessor,
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

        $ids = array_map(fn (object $item): string => $this->extractId($item), $related);
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

        return $this->accessor->getValue($resource, $propertyPath);
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

        $total = count($items);

        // Apply pagination
        $offset = ($criteria->pagination->number - 1) * $criteria->pagination->size;
        $paginatedItems = array_slice($items, $offset, $criteria->pagination->size);

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

        $this->em->flush();
    }

    public function replaceToMany(string|object $type, string $idOrRel, mixed $relOrTargets, array $targets = []): void
    {
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

        $this->em->flush();
    }

    public function addToMany(string|object $type, string $idOrRel, mixed $relOrTargets, array $targets = []): void
    {
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

        $this->em->flush();
    }

    public function removeFromToMany(string|object $type, string $idOrRel, mixed $relOrTargets, array $targets = []): void
    {
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

        $this->em->flush();
    }

    // ==================== Private helpers ====================

    private function findResource(string $type, string $id): object
    {
        $metadata = $this->registry->getByType($type);
        $entityClass = $metadata->class;

        $entity = $this->em->find($entityClass, $id);

        if ($entity === null) {
            throw new NotFoundException(
                sprintf('Resource "%s" with id "%s" not found', $type, $id)
            );
        }

        return $entity;
    }

    private function extractId(object $entity): string
    {
        $metadata = $this->em->getClassMetadata($entity::class);
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

        return (string) $id;
    }

    private function getClassMetadata(object $entity): ClassMetadata
    {
        return $this->em->getClassMetadata($entity::class);
    }

    private function findRelatedEntity(string $entityClass, string $id): object
    {
        $entity = $this->em->find($entityClass, $id);

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

    private function resolveTargetEntity(ClassMetadata $metadata, string $relationship): string
    {
        if (!$metadata->hasAssociation($relationship)) {
            throw new \RuntimeException(sprintf(
                'Property "%s" is not an association in entity "%s"',
                $relationship,
                $metadata->getName()
            ));
        }

        $mapping = $metadata->getAssociationMapping($relationship);

        if (is_array($mapping)) {
            if (!isset($mapping['targetEntity'])) {
                throw new \RuntimeException(sprintf(
                    'Association metadata for "%s" on "%s" does not contain targetEntity information.',
                    $relationship,
                    $metadata->getName()
                ));
            }

            /** @var string $target */
            $target = $mapping['targetEntity'];

            return $target;
        }

        if (is_object($mapping)) {
            if (method_exists($mapping, 'getTargetEntity')) {
                /** @var string $target */
                $target = $mapping->getTargetEntity();

                return $target;
            }

            if (property_exists($mapping, 'targetEntity')) {
                /** @var string $target */
                $target = $mapping->targetEntity;

                return $target;
            }
        }

        throw new \RuntimeException(sprintf(
            'Unable to determine target entity for association "%s" on "%s".',
            $relationship,
            $metadata->getName()
        ));
    }

    private function determineTargetClass(RelationshipMetadata $relationship, ClassMetadata $entityMetadata, string $propertyPath): string
    {
        if ($relationship->targetClass !== null && class_exists($relationship->targetClass)) {
            return $relationship->targetClass;
        }

        if ($relationship->targetType !== null && $this->registry->hasType($relationship->targetType)) {
            return $this->registry->getByType($relationship->targetType)->class;
        }

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
     * @return array{object, ResourceMetadata, string, array<int, ResourceIdentifier|string>}
     */
    private function resolveToManyUpdateArguments(string|object $typeOrResource, string $idOrRel, mixed $relOrTargets, array $targets): array
    {
        if (is_object($typeOrResource)) {
            if (!is_array($relOrTargets)) {
                throw new InvalidArgumentException('Targets must be provided as an array when passing a resource instance.');
            }

            $metadata = $this->requireMetadataForObject($typeOrResource);

            return [$typeOrResource, $metadata, $idOrRel, $relOrTargets];
        }

        if (!is_string($relOrTargets)) {
            throw new InvalidArgumentException('Relationship name must be provided as a string.');
        }

        $resource = $this->findResource($typeOrResource, $idOrRel);
        $metadata = $this->registry->getByType($typeOrResource);

        return [$resource, $metadata, $relOrTargets, $targets];
    }

    /**
     * @param RelationshipMetadata $relationship
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
     * @param RelationshipMetadata         $relationship
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
}
