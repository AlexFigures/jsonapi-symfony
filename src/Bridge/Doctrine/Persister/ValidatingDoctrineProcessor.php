<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Bridge\Doctrine\Persister;

use AlexFigures\JsonApi\Bridge\Doctrine\Flush\FlushManager;
use AlexFigures\JsonApi\Bridge\Doctrine\Instantiator\SerializerEntityInstantiator;
use AlexFigures\JsonApi\Contract\Data\ChangeSet;
use AlexFigures\JsonApi\Contract\Data\ResourceProcessor;
use AlexFigures\JsonApi\Http\Exception\ConflictException;
use AlexFigures\JsonApi\Http\Exception\NotFoundException;
use AlexFigures\JsonApi\Http\Validation\ConstraintViolationMapper;
use AlexFigures\JsonApi\Resource\Registry\ResourceRegistryInterface;
use AlexFigures\JsonApi\Resource\Relationship\RelationshipResolver;
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
 * @internal
 */
final readonly class ValidatingDoctrineProcessor implements ResourceProcessor
{
    public function __construct(
        private ManagerRegistry $managerRegistry,
        private ResourceRegistryInterface $registry,
        private PropertyAccessorInterface $accessor,
        private ValidatorInterface $validator,
        private ConstraintViolationMapper $violationMapper,
        private SerializerEntityInstantiator $instantiator,
        private RelationshipResolver $relationshipResolver,
        private FlushManager $flushManager,
        private ?\AlexFigures\JsonApi\Bridge\Doctrine\Profile\ProfileWriteHooks $profileHooks = null,
        private ?DoctrineWriteRequestMapper $writeRequests = null,
    ) {
    }

    public function processCreate(string $type, ChangeSet $changes, ?string $clientId = null): object
    {
        $metadata = $this->registry->getByType($type);
        $entityClass = $metadata->getDataClass();
        $em = $this->getEntityManagerFor($entityClass);

        // Check for ID conflict
        if ($clientId !== null && $em->find($entityClass, \AlexFigures\JsonApi\Bridge\Doctrine\Identifier\IdentifierConverter::convert($em, $entityClass, $clientId))) {
            throw new ConflictException(
                sprintf('Resource "%s" with id "%s" already exists.', $type, $clientId)
            );
        }

        // Create new entity through SerializerEntityInstantiator
        try {
            $mapped = $this->writeRequests?->map($metadata, $changes, 'create');
            $result = $mapped === null ? $this->instantiator->instantiate($entityClass, $metadata, $changes, isCreate: true)
                : ['entity' => $mapped, 'remainingChanges' => new ChangeSet([], $changes->relationships)];
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
        } catch (PartialDenormalizationException|NotNormalizableValueException|ExtraAttributesException|\ValueError|\InvalidArgumentException $e) {
            // Handle denormalization errors from strict mode
            // ValueError is thrown by BackedEnumNormalizer for invalid enum values
            // InvalidArgumentException wraps ValueError from BackedEnumNormalizer
            throw $this->violationMapper->mapDenormErrors($type, $e);
        }
        $entity = $result['entity'];
        $remainingChanges = $result['remainingChanges'];

        $idPath = $metadata->idPropertyPath ?? 'id';
        $classMetadata = $em->getClassMetadata($entityClass);

        // Set ID if needed
        if ($clientId !== null) {
            $this->accessor->setValue($entity, $idPath, \AlexFigures\JsonApi\Bridge\Doctrine\Identifier\IdentifierConverter::convert($em, $entityClass, $clientId));
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

        $this->profileHooks?->apply($entity, $metadata, true, $changes);
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
        $entity = $em->find($entityClass, \AlexFigures\JsonApi\Bridge\Doctrine\Identifier\IdentifierConverter::convert($em, $entityClass, $id));

        if ($entity === null) {
            throw new NotFoundException(
                sprintf('Resource "%s" with id "%s" not found.', $type, $id)
            );
        }

        // Apply attributes and relationships through strict Serializer denormalization
        $mapped = $this->writeRequests?->map($metadata, $changes, 'update', $entity);
        $this->denormalizeInto($entity, $mapped === null ? $changes : new ChangeSet([], $changes->relationships), $metadata, false);
        $profileRelationships = $this->profileHooks?->apply($entity, $metadata, false, $changes) ?? [];
        $toOneRelationships = $this->getToOneRelationshipValues($entity, array_replace($changes->relationships, $profileRelationships), $metadata);
        // Validate before flush
        $this->validateWithGroups($entity, $type, $metadata, false);

        // Restore resolved values overwritten by EAGER hydration during validation.
        // Doctrine field access avoids running setters and inverse synchronization twice.
        if ($toOneRelationships !== []) {
            $classMetadata = $em->getClassMetadata($entityClass);
            foreach ($toOneRelationships as $field => $value) {
                if ($classMetadata->getFieldValue($entity, $field) !== $value) {
                    $classMetadata->setFieldValue($entity, $field, $value);
                }
            }
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
        $entity = $em->find($entityClass, \AlexFigures\JsonApi\Bridge\Doctrine\Identifier\IdentifierConverter::convert($em, $entityClass, $id));

        if ($entity === null) {
            throw new NotFoundException(
                sprintf('Resource "%s" with id "%s" not found.', $type, $id)
            );
        }

        $this->profileHooks?->beforeDelete($type, $id);
        // Mark entity for removal and schedule flush
        if (!$this->profileHooks?->softDelete($entity, $metadata)) {
            $em->remove($entity);
        }
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
        \AlexFigures\JsonApi\Resource\Metadata\ResourceMetadata $metadata,
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
        \AlexFigures\JsonApi\Resource\Metadata\ResourceMetadata $metadata,
        bool $isCreate
    ): void {
        // Create ChangeSet with only attributes for denormalization
        $attributesOnlyChanges = new ChangeSet(attributes: $changes->attributes);
        $data = $this->instantiator->prepareDataForDenormalization($attributesOnlyChanges, $metadata);

        // Get denormalization groups from metadata
        $groups = $metadata->getDenormalizationGroups();

        // Start with denormalizationContext from resource metadata
        // This allows users to configure serializer options like skip_null_values
        $context = $metadata->denormalizationContext;

        // Merge with required options (these override user settings for correctness)
        $context[AbstractNormalizer::OBJECT_TO_POPULATE] = $entity;
        $context[AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES] = false; // Strict mode: reject unknown attributes
        $context[AbstractNormalizer::COLLECT_DENORMALIZATION_ERRORS] = true;
        $context['deep_object_to_populate'] = true; // Enable deep updates for embeddables and nested objects
        $context[AbstractNormalizer::GROUPS] = $groups;
        $context['is_create'] = $isCreate; // Pass operation type for relationship resolver

        try {
            $this->instantiator->denormalizer()->denormalize(
                $data,
                $metadata->getDataClass(),
                null,
                $context
            );
        } catch (PartialDenormalizationException|NotNormalizableValueException|ExtraAttributesException|\ValueError|\InvalidArgumentException $e) {
            // ValueError is thrown by BackedEnumNormalizer for invalid enum values
            // InvalidArgumentException wraps ValueError from BackedEnumNormalizer
            throw $this->violationMapper->mapDenormErrors($metadata->type, $e);
        }
    }



    private function findAttributeMetadata(
        \AlexFigures\JsonApi\Resource\Metadata\ResourceMetadata $metadata,
        string $path
    ): ?\AlexFigures\JsonApi\Resource\Metadata\AttributeMetadata {
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
     * @throws \AlexFigures\JsonApi\Http\Exception\ValidationException
     */
    private function validateWithGroups(
        object $entity,
        string $type,
        \AlexFigures\JsonApi\Resource\Metadata\ResourceMetadata $metadata,
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
     * Captures resolved to-one values before validation can rehydrate associations.
     * Only relationships present in the update payload are restored afterwards.
     *
     * @param  array<string, array{data: mixed}> $relationshipsPayload
     * @return array<string, mixed>              Values keyed by their Doctrine association field
     */
    private function getToOneRelationshipValues(
        object $entity,
        array $relationshipsPayload,
        \AlexFigures\JsonApi\Resource\Metadata\ResourceMetadata $metadata
    ): array {
        if ($relationshipsPayload === []) {
            return [];
        }

        $em = $this->getEntityManagerFor($metadata->getDataClass());
        $classMetadata = $em->getClassMetadata($metadata->getDataClass());
        $values = [];

        foreach ($relationshipsPayload as $relationshipName => $_relationshipData) {
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
            if (!$classMetadata->isSingleValuedAssociation($field)) {
                continue;
            }

            $values[$field] = $classMetadata->getFieldValue($entity, $field);
        }

        return $values;
    }
}
