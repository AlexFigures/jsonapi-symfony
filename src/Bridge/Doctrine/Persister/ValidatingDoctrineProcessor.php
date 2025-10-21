<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Bridge\Doctrine\Persister;

use AlexFigures\Symfony\Bridge\Doctrine\Flush\FlushManager;
use AlexFigures\Symfony\Bridge\Doctrine\Instantiator\SerializerEntityInstantiator;
use AlexFigures\Symfony\Contract\Data\ChangeSet;
use AlexFigures\Symfony\Contract\Data\ResourceProcessor;
use AlexFigures\Symfony\Http\Exception\ConflictException;
use AlexFigures\Symfony\Http\Exception\NotFoundException;
use AlexFigures\Symfony\Http\Validation\ConstraintViolationMapper;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
use AlexFigures\Symfony\Resource\Relationship\RelationshipResolver;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use RuntimeException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Doctrine Processor with automatic validation through Symfony Validator.
 *
 * Validates entities before persisting using Symfony Validator.
 *
 * Uses Symfony Validator constraints on Entity:
 * - #[Assert\NotBlank]
 * - #[Assert\Length]
 * - #[Assert\Email]
 * - etc.
 *
 * Supports entities with constructors requiring parameters
 * through SerializerEntityInstantiator (uses Symfony Serializer, like API Platform).
 *
 * This processor does NOT call flush() - flushing is handled by WriteListener.
 */
final class ValidatingDoctrineProcessor implements ResourceProcessor
{
    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
        private readonly ResourceRegistryInterface $registry,
        private readonly PropertyAccessorInterface $accessor,
        private readonly ValidatorInterface $validator,
        private readonly ConstraintViolationMapper $violationMapper,
        private readonly SerializerEntityInstantiator $instantiator,
        private readonly RelationshipResolver $relationshipResolver,
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
        try {
            $result = $this->instantiator->instantiate($entityClass, $metadata, $changes, isCreate: true);
        } catch (MissingConstructorArgumentsException $exception) {
            $violations = new ConstraintViolationList();

            foreach ($exception->getMissingConstructorArguments() as $argument) {
                $attributeMetadata = $this->findAttributeMetadata($metadata, $argument);
                $propertyPath = $argument;

                if ($attributeMetadata !== null) {
                    $propertyPath = $attributeMetadata->propertyPath ?? $attributeMetadata->name;
                }

                $violations->add(new ConstraintViolation(
                    'This value is required.',
                    'This value is required.',
                    [],
                    null,
                    $propertyPath,
                    null
                ));
            }

            throw $this->violationMapper->mapToException($type, $violations);
        } catch (PartialDenormalizationException|NotNormalizableValueException|ExtraAttributesException $e) {
            // Handle denormalization errors from strict mode
            throw $this->violationMapper->mapDenormErrors($type, $e);
        }
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

        // Apply remaining attributes and relationships through strict Serializer denormalization
        $this->denormalizeInto($entity, $remainingChanges, $metadata, true);

        // Validate before persist
        $this->validateWithGroups($entity, $type, $metadata, true);

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

        // Apply attributes and relationships through strict Serializer denormalization
        $this->denormalizeInto($entity, $changes, $metadata, false);

        // Validate before flush
        $this->validateWithGroups($entity, $type, $metadata, false);

        // Re-apply to-one relationships to restore null values that may have been
        // overwritten by Doctrine's eager loading during validation
        if (!empty($changes->relationships)) {
            $this->resyncToOneRelationships($entity, $changes->relationships, $metadata);
        }

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
     * Applies changes to entity through strict Serializer denormalization.
     *
     * Uses strict mode with error collection to catch all denormalization issues.
     */
    private function denormalizeInto(
        object $entity,
        ChangeSet $changes,
        \AlexFigures\Symfony\Resource\Metadata\ResourceMetadata $metadata,
        bool $isCreate
    ): void {

        // Denormalize attributes if present
        if (!empty($changes->attributes)) {
            $this->denormalizeAttributes($entity, $changes, $metadata, $isCreate);
        }

        // Apply relationships if present
        if (!empty($changes->relationships)) {
            $this->relationshipResolver->applyRelationships($entity, $changes->relationships, $metadata, $isCreate);
        }
    }

    private function denormalizeAttributes(
        object $entity,
        ChangeSet $changes,
        \AlexFigures\Symfony\Resource\Metadata\ResourceMetadata $metadata,
        bool $isCreate
    ): void {
        // Create ChangeSet with only attributes for denormalization
        $attributesOnlyChanges = new ChangeSet(attributes: $changes->attributes);
        $data = $this->instantiator->prepareDataForDenormalization($attributesOnlyChanges, $metadata);

        // Get denormalization groups from metadata
        $groups = $metadata->getDenormalizationGroups();

        $context = [
            AbstractNormalizer::OBJECT_TO_POPULATE => $entity,
            AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false, // Strict mode: reject unknown attributes
            AbstractNormalizer::COLLECT_DENORMALIZATION_ERRORS => true,
            'deep_object_to_populate' => true, // Enable deep updates for embeddables and nested objects
            AbstractNormalizer::GROUPS => $groups,
            'is_create' => $isCreate, // Pass operation type for relationship resolver
        ];

        try {
            $this->instantiator->denormalizer()->denormalize(
                $data,
                $metadata->class,
                null,
                $context
            );
        } catch (PartialDenormalizationException|NotNormalizableValueException|ExtraAttributesException $e) {
            throw $this->violationMapper->mapDenormErrors($metadata->type, $e);
        }
    }



    private function findAttributeMetadata(
        \AlexFigures\Symfony\Resource\Metadata\ResourceMetadata $metadata,
        string $path
    ): ?\AlexFigures\Symfony\Resource\Metadata\AttributeMetadata {
        foreach ($metadata->attributes as $attribute) {
            if ($attribute->propertyPath === $path || $attribute->name === $path) {
                return $attribute;
            }
        }

        return null;
    }

    /**
     * Validates entity with denormalization groups and throws exception on errors.
     *
     * @throws \AlexFigures\Symfony\Http\Exception\ValidationException
     */
    private function validateWithGroups(
        object $entity,
        string $type,
        \AlexFigures\Symfony\Resource\Metadata\ResourceMetadata $metadata,
        bool $isCreate
    ): void {
        // Use denormalization groups from metadata (includes 'Default' automatically)
        $groups = $metadata->getDenormalizationGroups();

        // Ensure operation specific validation groups are always included
        $operationGroup = $isCreate ? 'create' : 'update';

        if (!in_array($operationGroup, $groups, true)) {
            $groups[] = $operationGroup;
        }

        $violations = $this->validator->validate($entity, null, $groups);

        if (count($violations) > 0) {
            // ConstraintViolationMapper converts violations to JSON:API errors
            throw $this->violationMapper->mapToException($type, $violations);
        }
    }

    /**
     * Re-applies to-one relationships after validation.
     *
     * This fixes an issue where Doctrine's eager loading during validation
     * can overwrite null values with old database values. When a to-one
     * relationship is set to null but configured as eager, Doctrine's
     * UnitOfWork may reload the old value from the database during validation
     * (e.g., when validators access relationship getters).
     *
     * This method re-applies only to-one relationships from the original
     * payload to ensure null values are preserved.
     *
     * @param array<string, array{data: mixed}> $relationshipsPayload
     */
    private function resyncToOneRelationships(
        object $entity,
        array $relationshipsPayload,
        \AlexFigures\Symfony\Resource\Metadata\ResourceMetadata $metadata
    ): void {
        $em = $this->getEntityManagerFor($metadata->getDataClass());
        $classMetadata = $em->getClassMetadata($metadata->getDataClass());

        foreach ($relationshipsPayload as $relationshipName => $relationshipData) {
            // Skip if relationship not in metadata
            if (!isset($metadata->relationships[$relationshipName])) {
                continue;
            }

            $relMeta = $metadata->relationships[$relationshipName];

            // Only process to-one relationships (to-many uses collections, no issue there)
            if ($relMeta->toMany) {
                continue;
            }

            $field = $relMeta->propertyPath ?? $relMeta->name;

            // Only process Doctrine associations
            if (!$classMetadata->hasAssociation($field)) {
                continue;
            }

            // Re-apply the relationship value
            $data = $relationshipData['data'] ?? null;

            // Only re-sync if explicitly set to null in the payload
            // (if data is not null, RelationshipResolver already set it correctly)
            if ($data === null) {
                $this->accessor->setValue($entity, $field, null);
            }
        }
    }
}
