<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Bridge\Serializer\Normalizer;

use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
use AlexFigures\Symfony\Resource\Relationship\RelationshipResolver;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Denormalizer for JSON:API relationships that integrates with Symfony Serializer
 * to provide consistent error handling and pointer generation.
 */
final class JsonApiRelationshipDenormalizer implements DenormalizerInterface
{
    public function __construct(
        private RelationshipResolver $relationshipResolver,
        private ResourceRegistryInterface $registry,
    ) {
    }

    /**
     * @param mixed                $data
     * @param class-string         $type
     * @param array<string, mixed> $context
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        if (!is_array($data) || !isset($data['relationships'])) {
            return $data;
        }

        $entity = $context['object_to_populate'] ?? null;
        if (!$entity) {
            throw new InvalidArgumentException('JsonApiRelationshipDenormalizer requires object_to_populate in context');
        }

        // Get resource metadata for the entity
        $metadata = null;
        foreach ($this->registry->all() as $resourceMetadata) {
            if ($resourceMetadata->dataClass === get_class($entity)) {
                $metadata = $resourceMetadata;
                break;
            }
        }

        if (!$metadata) {
            throw new InvalidArgumentException(sprintf(
                'No resource metadata found for class %s',
                get_class($entity)
            ));
        }

        // Determine if this is a create operation
        $isCreate = $context['is_create'] ?? false;

        // Apply relationships using the resolver
        $this->relationshipResolver->applyRelationships(
            $entity,
            $data['relationships'],
            $metadata,
            $isCreate
        );

        // Remove relationships from data so they don't interfere with attribute denormalization
        unset($data['relationships']);

        return $data;
    }

    /**
     * @param class-string         $type
     * @param array<string, mixed> $context
     */
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        // Support denormalization if:
        // 1. Data contains relationships
        // 2. We have an object to populate (entity)
        // 3. The entity is registered in our resource registry
        if (!is_array($data) || !isset($data['relationships'])) {
            return false;
        }

        $entity = $context['object_to_populate'] ?? null;
        if (!$entity) {
            return false;
        }

        // Check if entity class is registered
        foreach ($this->registry->all() as $resourceMetadata) {
            if ($resourceMetadata->dataClass === get_class($entity)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<class-string, bool>
     */
    public function getSupportedTypes(?string $format): array
    {
        return ['*' => false]; // We support any type but with low priority
    }
}
