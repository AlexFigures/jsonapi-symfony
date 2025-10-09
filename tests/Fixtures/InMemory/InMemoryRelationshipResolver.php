<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Fixtures\InMemory;

use JsonApi\Symfony\Resource\Metadata\ResourceMetadata;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * In-memory implementation of relationship resolver for functional tests.
 */
final class InMemoryRelationshipResolver
{
    private PropertyAccessorInterface $accessor;

    public function __construct(
        private readonly InMemoryRepository $repository,
        private readonly ResourceRegistryInterface $registry,
        ?PropertyAccessorInterface $accessor = null,
    ) {
        $this->accessor = $accessor ?? PropertyAccess::createPropertyAccessor();
    }

    /**
     * @param array<string, mixed> $relationshipsPayload
     */
    public function applyRelationships(
        object $entity,
        array $relationshipsPayload,
        ResourceMetadata $resourceMetadata,
        bool $isCreate
    ): void {
        foreach ($relationshipsPayload as $relationshipName => $relationshipData) {
            if (!isset($resourceMetadata->relationships[$relationshipName])) {
                continue;
            }

            $relationshipMetadata = $resourceMetadata->relationships[$relationshipName];
            $data = $relationshipData['data'] ?? null;

            if ($relationshipMetadata->toMany) {
                // Handle to-many relationship
                if (!is_array($data)) {
                    continue;
                }

                $relatedEntities = [];
                foreach ($data as $identifier) {
                    if (!is_array($identifier) || !isset($identifier['type'], $identifier['id'])) {
                        continue;
                    }

                    $relatedEntity = $this->repository->get($identifier['type'], $identifier['id']);
                    if ($relatedEntity !== null) {
                        $relatedEntities[] = $relatedEntity;
                    }
                }

                $propertyPath = $relationshipMetadata->propertyPath ?? $relationshipMetadata->name;
                $this->accessor->setValue($entity, $propertyPath, $relatedEntities);
            } else {
                // Handle to-one relationship
                if ($data === null) {
                    $propertyPath = $relationshipMetadata->propertyPath ?? $relationshipMetadata->name;
                    $this->accessor->setValue($entity, $propertyPath, null);
                    continue;
                }

                if (!is_array($data) || !isset($data['type'], $data['id'])) {
                    continue;
                }

                $relatedEntity = $this->repository->get($data['type'], $data['id']);
                if ($relatedEntity !== null) {
                    $propertyPath = $relationshipMetadata->propertyPath ?? $relationshipMetadata->name;
                    $this->accessor->setValue($entity, $propertyPath, $relatedEntity);
                }
            }
        }
    }
}
