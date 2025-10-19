<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Bridge\Doctrine\Persister;

use AlexFigures\Symfony\Bridge\Doctrine\Flush\FlushManager;
use AlexFigures\Symfony\Bridge\Doctrine\Instantiator\SerializerEntityInstantiator;
use AlexFigures\Symfony\Contract\Data\ChangeSet;
use AlexFigures\Symfony\Contract\Data\ResourceProcessor;
use AlexFigures\Symfony\Http\Exception\ConflictException;
use AlexFigures\Symfony\Http\Exception\NotFoundException;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use RuntimeException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Generic Doctrine processor for JSON:API resources.
 *
 * Handles entity creation, updating, and deletion.
 *
 * Supports entities with constructors requiring parameters
 * through SerializerEntityInstantiator (uses Symfony Serializer, like API Platform).
 *
 * This processor does NOT call flush() - flushing is handled by WriteListener.
 */
class GenericDoctrineProcessor implements ResourceProcessor
{
    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
        private readonly ResourceRegistryInterface $registry,
        private readonly PropertyAccessorInterface $accessor,
        private readonly SerializerEntityInstantiator $instantiator,
        private readonly FlushManager $flushManager,
    ) {
    }

    public function processCreate(string $type, ChangeSet $changes, ?string $clientId = null): object
    {
        $metadata = $this->registry->getByType($type);
        $entityClass = $metadata->getDataClass();
        $em = $this->getEntityManagerFor($entityClass);

        // Check for ID conflict
        if ($clientId !== null && $em->find($entityClass, $clientId)) {
            throw new ConflictException(
                sprintf('Resource "%s" with id "%s" already exists.', $type, $clientId)
            );
        }

        // Create new entity through SerializerEntityInstantiator
        // It can call constructors with parameters and considers SerializationGroups
        $result = $this->instantiator->instantiate($entityClass, $metadata, $changes, isCreate: true);
        $entity = $result['entity'];
        $remainingChanges = $result['remainingChanges'];

        $idPath = $metadata->idPropertyPath ?? 'id';
        $classMetadata = $em->getClassMetadata($entityClass);

        // Set ID if needed
        if ($clientId !== null) {
            $this->accessor->setValue($entity, $idPath, $clientId);
        } elseif ($classMetadata->isIdentifierNatural()) {
            // Check if ID is already set (e.g., in constructor)
            try {
                $currentId = $this->accessor->getValue($entity, $idPath);
                if ($currentId === null || $currentId === '') {
                    $this->accessor->setValue($entity, $idPath, Uuid::v4()->toRfc4122());
                }
            } catch (\Throwable) {
                // If unable to get ID, set a new one
                $this->accessor->setValue($entity, $idPath, Uuid::v4()->toRfc4122());
            }
        }

        // Apply remaining attributes considering serialization groups
        $this->applyAttributes($entity, $metadata, $remainingChanges, true);

        // Persist entity and schedule flush
        $em->persist($entity);
        $this->flushManager->scheduleFlush($entityClass);

        return $entity;
    }

    public function processUpdate(string $type, string $id, ChangeSet $changes): object
    {
        $metadata = $this->registry->getByType($type);
        $entityClass = $metadata->getDataClass();
        $em = $this->getEntityManagerFor($entityClass);
        $entity = $em->find($entityClass, $id);

        if ($entity === null) {
            throw new NotFoundException(
                sprintf('Resource "%s" with id "%s" not found.', $type, $id)
            );
        }

        // Apply attributes considering serialization groups
        $this->applyAttributes($entity, $metadata, $changes, false);

        // Entity is already managed, schedule flush
        $this->flushManager->scheduleFlush($entityClass);

        return $entity;
    }

    public function processDelete(string $type, string $id): void
    {
        $metadata = $this->registry->getByType($type);
        $entityClass = $metadata->getDataClass();
        $em = $this->getEntityManagerFor($entityClass);
        $entity = $em->find($entityClass, $id);

        if ($entity === null) {
            throw new NotFoundException(
                sprintf('Resource "%s" with id "%s" not found.', $type, $id)
            );
        }

        // Mark entity for removal and schedule flush
        $em->remove($entity);
        $this->flushManager->scheduleFlush($entityClass);
    }

    private function applyAttributes(
        object $entity,
        \AlexFigures\Symfony\Resource\Metadata\ResourceMetadata $metadata,
        ChangeSet $changes,
        bool $isCreate
    ): void {
        foreach ($changes->attributes as $path => $value) {
            // Note: Attribute writability is now controlled by Symfony Serializer's groups
            // during denormalization. This method receives already filtered changes.
            // No need to check isWritable() here anymore.

            $this->accessor->setValue($entity, $path, $value);
        }
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
