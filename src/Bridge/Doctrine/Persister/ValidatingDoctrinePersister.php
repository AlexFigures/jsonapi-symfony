<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Bridge\Doctrine\Persister;

use Doctrine\ORM\EntityManagerInterface;
use JsonApi\Symfony\Bridge\Doctrine\Instantiator\SerializerEntityInstantiator;
use JsonApi\Symfony\Contract\Data\ChangeSet;
use JsonApi\Symfony\Contract\Data\ResourcePersister;
use JsonApi\Symfony\Http\Exception\ConflictException;
use JsonApi\Symfony\Http\Exception\NotFoundException;
use JsonApi\Symfony\Http\Validation\ConstraintViolationMapper;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Doctrine Persister with automatic validation through Symfony Validator.
 *
 * Extends GenericDoctrinePersister by adding validation before persist().
 *
 * Uses Symfony Validator constraints on Entity:
 * - #[Assert\NotBlank]
 * - #[Assert\Length]
 * - #[Assert\Email]
 * - etc.
 *
 * Supports entities with constructors requiring parameters
 * through SerializerEntityInstantiator (uses Symfony Serializer, like API Platform).
 */
final class ValidatingDoctrinePersister implements ResourcePersister
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ResourceRegistryInterface $registry,
        private readonly PropertyAccessorInterface $accessor,
        private readonly ValidatorInterface $validator,
        private readonly ConstraintViolationMapper $violationMapper,
        private readonly SerializerEntityInstantiator $instantiator,
    ) {
    }

    public function create(string $type, ChangeSet $changes, ?string $clientId = null): object
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
        $result = $this->instantiator->instantiate($entityClass, $metadata, $changes, isCreate: true);
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

        // Apply remaining attributes considering serialization groups
        $this->applyAttributes($entity, $metadata, $remainingChanges, true);

        // Validate before persist
        $this->validate($entity, $type);

        $this->em->persist($entity);
        $this->em->flush();

        return $entity;
    }

    public function update(string $type, string $id, ChangeSet $changes): object
    {
        $metadata = $this->registry->getByType($type);
        $entity = $this->em->find($metadata->class, $id);

        if ($entity === null) {
            throw new NotFoundException(
                sprintf('Resource "%s" with id "%s" not found.', $type, $id)
            );
        }

        // Apply attributes considering serialization groups
        $this->applyAttributes($entity, $metadata, $changes, false);

        // Validate before flush
        $this->validate($entity, $type);

        $this->em->flush();

        return $entity;
    }

    public function delete(string $type, string $id): void
    {
        $metadata = $this->registry->getByType($type);
        $entity = $this->em->find($metadata->class, $id);

        if ($entity === null) {
            throw new NotFoundException(
                sprintf('Resource "%s" with id "%s" not found.', $type, $id)
            );
        }

        $this->em->remove($entity);
        $this->em->flush();
    }

    private function applyAttributes(
        object $entity,
        \JsonApi\Symfony\Resource\Metadata\ResourceMetadata $metadata,
        ChangeSet $changes,
        bool $isCreate
    ): void {
        foreach ($changes->attributes as $path => $value) {
            // Check if this attribute can be written
            $attributeMetadata = $this->findAttributeMetadata($metadata, $path);

            if ($attributeMetadata !== null && !$attributeMetadata->isWritable($isCreate)) {
                // Skip attributes that cannot be written
                continue;
            }

            $this->accessor->setValue($entity, $path, $value);
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
     * Validates entity and throws exception on errors.
     *
     * @throws \JsonApi\Symfony\Http\Exception\ValidationException
     */
    private function validate(object $entity, string $type): void
    {
        $violations = $this->validator->validate($entity);

        if (count($violations) > 0) {
            // ConstraintViolationMapper converts violations to JSON:API errors
            throw $this->violationMapper->mapToException($type, $violations);
        }
    }
}
