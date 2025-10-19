<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Bridge\Doctrine\Instantiator;

use AlexFigures\Symfony\Contract\Data\ChangeSet;
use AlexFigures\Symfony\Resource\Metadata\ResourceMetadata;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use RuntimeException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Entity instantiator that relies on the Symfony Serializer.
 *
 * Mirrors the strategy used in API Platform:
 * - Uses the ObjectNormalizer from the Symfony Serializer
 * - Automatically handles constructors with parameters
 * - Supports every capability provided by the Symfony Serializer
 * - Avoids bespoke reflection logic
 *
 * Benefits:
 * - Less code
 * - Better type inference
 * - Automatic constructor handling
 * - Full compatibility with API Platform
 * - Supports custom denormalizers
 *
 * How it works:
 * 1. Converts the ChangeSet into an array of data
 * 2. Calls Serializer::denormalize() to create the object
 * 3. Lets the serializer invoke the constructor with arguments
 * 4. Applies remaining properties through setters/property access
 */
final class SerializerEntityInstantiator
{
    private readonly Serializer $serializer;

    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
        private readonly PropertyAccessorInterface $accessor,
    ) {
        // Create a PropertyInfoExtractor to resolve types
        // Rely only on ReflectionExtractor (no phpdocumentor/reflection-docblock required)
        $reflectionExtractor = new ReflectionExtractor();

        $propertyInfo = new PropertyInfoExtractor(
            [$reflectionExtractor],  // listExtractors
            [$reflectionExtractor],  // typeExtractors
            [],                      // descriptionExtractors (not needed)
            [$reflectionExtractor],  // accessExtractors
            [$reflectionExtractor]   // initializableExtractors
        );

        // Create ClassMetadataFactory for strict attribute validation
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());

        // Build an ObjectNormalizer with constructor support
        $normalizer = new ObjectNormalizer(
            $classMetadataFactory, // ClassMetadataFactory (required for ALLOW_EXTRA_ATTRIBUTES = false)
            null, // NameConverter (not required)
            $this->accessor, // PropertyAccessor for setting properties
            $propertyInfo, // PropertyInfo for determining types
            null, // ClassDiscriminator (not required)
            null, // ObjectClassResolver (not required)
        );

        // Create the Serializer with the ObjectNormalizer
        $this->serializer = new Serializer([$normalizer]);
    }

    /**
     * Creates an entity instance via the Symfony Serializer.
     *
     * @param class-string $entityClass
     * @param bool         $isCreate true for POST (create), false for PATCH (update)
     *
     * @return array{entity: object, remainingChanges: ChangeSet}
     */
    public function instantiate(
        string $entityClass,
        ResourceMetadata $metadata,
        ChangeSet $changes,
        bool $isCreate = true,
    ): array {
        if (!class_exists($entityClass)) {
            throw new RuntimeException(sprintf('Cannot instantiate unknown entity class "%s".', $entityClass));
        }

        /** @var class-string $entityClass */
        $entityClass = $entityClass;

        $reflection = new \ReflectionClass($entityClass);
        $constructor = $reflection->getConstructor();

        // Fallback: instantiate without the serializer when there is no constructor
        if ($constructor === null) {
            $classMetadata = $this->getEntityManagerFor($entityClass)->getClassMetadata($entityClass);
            $entity = $classMetadata->newInstance();

            return [
                'entity' => $entity,
                'remainingChanges' => $changes,
            ];
        }

        // If constructor has no parameters, call it directly to ensure initialization
        if ($constructor->getNumberOfParameters() === 0) {
            $entity = new $entityClass();

            return [
                'entity' => $entity,
                'remainingChanges' => $changes,
            ];
        }

        // Prepare data for denormalisation
        // No need to filter by groups - Symfony Serializer will do it automatically
        $data = $this->prepareDataForDenormalization($changes, $metadata);

        // Get denormalization groups from metadata
        $groups = $metadata->getDenormalizationGroups();

        // Use the Symfony Serializer to build the object
        // It automatically calls the constructor with the correct arguments
        // and filters attributes by groups
        $entity = $this->serializer->denormalize(
            $data,
            $entityClass,
            null,
            [
                // Strict mode: reject unknown attributes
                AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false,
                // Collect all denormalization errors for better error reporting
                AbstractNormalizer::COLLECT_DENORMALIZATION_ERRORS => true,
                // Serialization groups for filtering
                AbstractNormalizer::GROUPS => $groups,
            ]
        );

        if (!is_object($entity)) {
            throw new RuntimeException(sprintf('Serializer failed to create an instance of "%s".', $entityClass));
        }

        /** @var object $entity */
        $entity = $entity;

        // Determine which attributes were consumed by the constructor
        $usedAttributes = $this->getConstructorParameters($constructor, $changes, $metadata);

        // Build a new ChangeSet without constructor-consumed attributes
        $remainingAttributes = [];
        foreach ($changes->attributes as $path => $value) {
            if (!in_array($path, $usedAttributes, true)) {
                $remainingAttributes[$path] = $value;
            }
        }

        return [
            'entity' => $entity,
            'remainingChanges' => new ChangeSet(
                attributes: $remainingAttributes,
                relationships: $changes->relationships
            ),
        ];
    }

    /**
     * Prepares data from the ChangeSet for the Symfony Serializer.
     *
     * @return array<string, mixed>
     */
    public function prepareDataForDenormalization(
        ChangeSet $changes,
        ResourceMetadata $metadata
    ): array {
        $data = [];

        foreach ($changes->attributes as $path => $value) {
            // Look up attribute metadata by property path (same logic as filterBySerializationGroups)
            $attributeMetadata = $this->findAttributeMetadata($metadata, $path);

            if ($attributeMetadata !== null) {
                $propertyPath = $attributeMetadata->propertyPath ?? $attributeMetadata->name;
            } else {
                $propertyPath = $path;
            }

            $data[$propertyPath] = $value;
        }

        return $data;
    }

    /**
     * Returns the Symfony Serializer instance.
     */
    public function serializer(): SerializerInterface
    {
        return $this->serializer;
    }

    /**
     * Returns the Symfony Denormalizer instance.
     */
    public function denormalizer(): DenormalizerInterface
    {
        return $this->serializer;
    }

    /**
     * Finds attribute metadata by property path or name.
     *
     * Security-sensitive: when an attribute is renamed with #[Attribute(name: 'new-name')],
     * metadata is indexed by the new name while the ChangeSet keeps the property path.
     * Without this lookup, attributes with SerializationGroups could be mishandled.
     */
    private function findAttributeMetadata(
        ResourceMetadata $metadata,
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
     * Returns the constructor parameters that were consumed.
     *
     * @return list<string>
     */
    private function getConstructorParameters(
        \ReflectionMethod $constructor,
        ChangeSet $changes,
        ResourceMetadata $metadata
    ): array {
        $usedAttributes = [];

        foreach ($constructor->getParameters() as $parameter) {
            $paramName = $parameter->getName();

            // Direct match: constructor parameter is present in the ChangeSet
            if (isset($changes->attributes[$paramName])) {
                $usedAttributes[] = $paramName;
                continue;
            }

            // Fallback: check via propertyPath in metadata
            foreach ($metadata->attributes as $attributeName => $attributeMetadata) {
                $propertyPath = $attributeMetadata->propertyPath ?? $attributeName;

                if ($propertyPath === $paramName && isset($changes->attributes[$attributeName])) {
                    $usedAttributes[] = $attributeName;
                    break;
                }
            }
        }

        return $usedAttributes;
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
