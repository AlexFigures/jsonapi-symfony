<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Bridge\Doctrine\Persister;

use Doctrine\ORM\EntityManagerInterface;
use JsonApi\Symfony\Bridge\Doctrine\Flush\FlushManager;
use JsonApi\Symfony\Bridge\Doctrine\Instantiator\SerializerEntityInstantiator;
use JsonApi\Symfony\Contract\Data\ChangeSet;
use JsonApi\Symfony\Contract\Data\ResourceProcessor;
use JsonApi\Symfony\Http\Exception\ConflictException;
use JsonApi\Symfony\Http\Exception\NotFoundException;
use JsonApi\Symfony\Http\Validation\ConstraintViolationMapper;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;
use JsonApi\Symfony\Resource\Relationship\RelationshipResolver;
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
        private readonly EntityManagerInterface $em,
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
        $entityClass = $metadata->class;

        // Check for ID conflict
        if ($clientId !== null && $this->em->find($entityClass, $clientId)) {
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
                $propertyPath = $attributeMetadata?->propertyPath ?? $argument;

                $violations->add(new ConstraintViolation(
                    'This field is required.',
                    'This field is required.',
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
        $classMetadata = $this->em->getClassMetadata($entityClass);

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

        // Validate before persist with create groups
        $this->validateWithGroups($entity, $type, $metadata, true);

        // Persist entity and schedule flush
        $this->em->persist($entity);
        $this->flushManager->scheduleFlush();

        return $entity;
    }

    public function processUpdate(string $type, string $id, ChangeSet $changes): object
    {
        $metadata = $this->registry->getByType($type);
        $entity = $this->em->find($metadata->class, $id);

        if ($entity === null) {
            throw new NotFoundException(
                sprintf('Resource "%s" with id "%s" not found.', $type, $id)
            );
        }

        // Apply attributes and relationships through strict Serializer denormalization
        $this->denormalizeInto($entity, $changes, $metadata, false);

        // Validate before flush with update groups
        $this->validateWithGroups($entity, $type, $metadata, false);

        // Entity is already managed, schedule flush
        $this->flushManager->scheduleFlush();

        return $entity;
    }

    public function processDelete(string $type, string $id): void
    {
        $metadata = $this->registry->getByType($type);
        $entity = $this->em->find($metadata->class, $id);

        if ($entity === null) {
            throw new NotFoundException(
                sprintf('Resource "%s" with id "%s" not found.', $type, $id)
            );
        }

        // Mark entity for removal and schedule flush
        $this->em->remove($entity);
        $this->flushManager->scheduleFlush();
    }

    /**
     * Applies changes to entity through strict Serializer denormalization.
     *
     * Uses strict mode with error collection to catch all denormalization issues.
     */
    private function denormalizeInto(
        object $entity,
        ChangeSet $changes,
        \JsonApi\Symfony\Resource\Metadata\ResourceMetadata $metadata,
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
        \JsonApi\Symfony\Resource\Metadata\ResourceMetadata $metadata,
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
        \JsonApi\Symfony\Resource\Metadata\ResourceMetadata $metadata,
        string $path
    ): ?\JsonApi\Symfony\Resource\Metadata\AttributeMetadata {
        foreach ($metadata->attributes as $attribute) {
            if ($attribute->propertyPath === $path || $attribute->name === $path) {
                return $attribute;
            }
        }

        return null;
    }

    /**
     * Validates entity with operation-specific groups and throws exception on errors.
     *
     * @throws \JsonApi\Symfony\Http\Exception\ValidationException
     */
    private function validateWithGroups(
        object $entity,
        string $type,
        \JsonApi\Symfony\Resource\Metadata\ResourceMetadata $metadata,
        bool $isCreate
    ): void {
        // Use validation groups from metadata or defaults
        $operationGroups = $metadata->getOperationGroups();
        $groups = $operationGroups->getValidationGroups($isCreate);

        $violations = $this->validator->validate($entity, null, $groups);

        if (count($violations) > 0) {
            // ConstraintViolationMapper converts violations to JSON:API errors
            throw $this->violationMapper->mapToException($type, $violations);
        }
    }
}
