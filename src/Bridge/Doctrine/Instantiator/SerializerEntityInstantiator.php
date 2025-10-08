<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Bridge\Doctrine\Instantiator;

use Doctrine\ORM\EntityManagerInterface;
use JsonApi\Symfony\Contract\Data\ChangeSet;
use JsonApi\Symfony\Resource\Metadata\ResourceMetadata;
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
        private readonly EntityManagerInterface $em,
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
     * @param bool $isCreate true for POST (create), false for PATCH (update)
     * @return array{entity: object, remainingChanges: ChangeSet}
     */
    public function instantiate(
        string $entityClass,
        ResourceMetadata $metadata,
        ChangeSet $changes,
        bool $isCreate = true,
    ): array {
        $reflection = new \ReflectionClass($entityClass);
        $constructor = $reflection->getConstructor();

        // Fallback: instantiate without the serializer when there is no constructor or it has no parameters
        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
            $classMetadata = $this->em->getClassMetadata($entityClass);
            $entity = $classMetadata->newInstance();

            return [
                'entity' => $entity,
                'remainingChanges' => $changes,
            ];
        }

        // Filter attributes by serialization groups
        $filteredChanges = $this->filterBySerializationGroups($changes, $metadata, $isCreate);

        // Prepare data for denormalisation
        $data = $this->prepareDataForDenormalization($filteredChanges, $metadata);

        // Use the Symfony Serializer to build the object
        // It automatically calls the constructor with the correct arguments
        $entity = $this->serializer->denormalize(
            $data,
            $entityClass,
            null,
            [
                // Strict mode: reject unknown attributes
                AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false,
                // Collect all denormalization errors for better error reporting
                AbstractNormalizer::COLLECT_DENORMALIZATION_ERRORS => true,
                // Ignore properties that cannot be set
                AbstractNormalizer::IGNORED_ATTRIBUTES => [],
            ]
        );

        // Determine which attributes were consumed by the constructor
        $usedAttributes = $this->getConstructorParameters($constructor, $filteredChanges, $metadata);

        // Build a new ChangeSet without constructor-consumed attributes
        $remainingAttributes = [];
        foreach ($filteredChanges->attributes as $path => $value) {
            if (!in_array($path, $usedAttributes, true)) {
                $remainingAttributes[$path] = $value;
            }
        }

        return [
            'entity' => $entity,
            'remainingChanges' => new ChangeSet(
                attributes: $remainingAttributes,
                relationships: $filteredChanges->relationships
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
            $propertyPath = $attributeMetadata?->propertyPath ?? $path;

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
     * Filters attributes according to SerializationGroups.
     *
     * Considers the 'write', 'create', and 'update' groups:
     * - 'write': always writable (POST and PATCH)
     * - 'create': writable during creation only (POST)
     * - 'update': writable during updates only (PATCH)
     */
    private function filterBySerializationGroups(
        ChangeSet $changes,
        ResourceMetadata $metadata,
        bool $isCreate
    ): ChangeSet {
        $filteredAttributes = [];

        foreach ($changes->attributes as $path => $value) {
            // Look up attribute metadata by property path (same as GenericDoctrinePersister)
            $attributeMetadata = $this->findAttributeMetadata($metadata, $path);

            // If metadata is missing, keep the attribute (permissive by default)
            if ($attributeMetadata === null) {
                $filteredAttributes[$path] = $value;
                continue;
            }

            // Check whether the attribute is writable
            if ($attributeMetadata->isWritable($isCreate)) {
                $filteredAttributes[$path] = $value;
            }
            // Non-writable attributes are ignored
        }

        // Preserve relationships from original ChangeSet
        return new ChangeSet(
            attributes: $filteredAttributes,
            relationships: $changes->relationships
        );
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
    ): ?\JsonApi\Symfony\Resource\Metadata\AttributeMetadata {
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
}
