<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Fixtures\InMemory;

use AlexFigures\Symfony\Contract\Data\RelationshipUpdater;
use AlexFigures\Symfony\Contract\Data\ResourceIdentifier;
use AlexFigures\Symfony\Http\Exception\NotFoundException;
use AlexFigures\Symfony\Resource\Metadata\RelationshipMetadata;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
use ReflectionClass;
use ReflectionException;
use Stringable;

final class InMemoryRelationshipUpdater implements RelationshipUpdater
{
    public function __construct(
        private readonly ResourceRegistryInterface $registry,
        private readonly InMemoryRepository $repository,
    ) {
    }

    public function replaceToOne(string $type, string $id, string $rel, ?ResourceIdentifier $target): void
    {
        $model = $this->requireModel($type, $id);
        $relationship = $this->requireRelationship($type, $rel);
        $value = null;

        if ($target !== null) {
            $value = $this->requireModel($target->type, $target->id);
        }

        $this->setRelationshipValue($model, $relationship, $value);
        $this->repository->save($type, $model);
    }

    /**
     * @param list<ResourceIdentifier> $targets
     */
    public function replaceToMany(string $type, string $id, string $rel, array $targets): void
    {
        $model = $this->requireModel($type, $id);
        $relationship = $this->requireRelationship($type, $rel);
        $items = [];

        foreach ($targets as $target) {
            $items[] = $this->requireModel($target->type, $target->id);
        }

        $this->setRelationshipValue($model, $relationship, $items);
        $this->repository->save($type, $model);
    }

    /**
     * @param list<ResourceIdentifier> $targets
     */
    public function addToMany(string $type, string $id, string $rel, array $targets): void
    {
        $model = $this->requireModel($type, $id);
        $relationship = $this->requireRelationship($type, $rel);
        $current = $this->getRelationshipItems($model, $relationship);
        $existingMap = $this->mapByIdentifier($current);

        foreach ($targets as $target) {
            $key = $target->type . ':' . $target->id;
            if (isset($existingMap[$key])) {
                continue;
            }

            $item = $this->requireModel($target->type, $target->id);
            $existingMap[$key] = $item;
        }

        $this->setRelationshipValue($model, $relationship, array_values($existingMap));
        $this->repository->save($type, $model);
    }

    /**
     * @param list<ResourceIdentifier> $targets
     */
    public function removeFromToMany(string $type, string $id, string $rel, array $targets): void
    {
        $model = $this->requireModel($type, $id);
        $relationship = $this->requireRelationship($type, $rel);
        $current = $this->getRelationshipItems($model, $relationship);
        $removeKeys = [];

        foreach ($targets as $target) {
            $removeKeys[$target->type . ':' . $target->id] = true;
        }

        $filtered = [];
        foreach ($current as $item) {
            $metadata = $this->registry->getByClass($item::class);
            if ($metadata === null) {
                continue;
            }

            $idValue = $this->repository->propertyAccessor()->getValue($item, $metadata->idPropertyPath ?? 'id');
            $stringId = $this->stringifyId($idValue);
            if ($stringId === null) {
                continue;
            }

            $key = $metadata->type . ':' . $stringId;
            if (isset($removeKeys[$key])) {
                continue;
            }

            $filtered[] = $item;
        }

        $this->setRelationshipValue($model, $relationship, $filtered);
        $this->repository->save($type, $model);
    }

    private function requireModel(string $type, string $id): object
    {
        $model = $this->repository->get($type, $id);
        if ($model === null) {
            throw new NotFoundException(sprintf('Resource "%s" with id "%s" was not found.', $type, $id));
        }

        return $model;
    }

    private function requireRelationship(string $type, string $name): RelationshipMetadata
    {
        $metadata = $this->registry->getByType($type);
        if (!isset($metadata->relationships[$name])) {
            throw new NotFoundException(sprintf('Relationship "%s" not found on resource "%s".', $name, $type));
        }

        /** @var RelationshipMetadata $relationship */
        $relationship = $metadata->relationships[$name];

        return $relationship;
    }

    /**
     * @return list<object>
     */
    private function getRelationshipItems(object $model, RelationshipMetadata $relationship): array
    {
        $value = $this->repository->propertyAccessor()->getValue($model, $relationship->propertyPath ?? $relationship->name);

        if ($value === null) {
            return [];
        }

        if (is_array($value)) {
            return array_values(array_filter($value, static fn ($item) => $item !== null && is_object($item)));
        }

        if ($value instanceof \Traversable) {
            $items = [];
            foreach ($value as $item) {
                if ($item !== null && is_object($item)) {
                    $items[] = $item;
                }
            }

            return $items;
        }

        return [];
    }

    /**
     * @param list<object> $items
     *
     * @return array<string, object>
     */
    private function mapByIdentifier(array $items): array
    {
        $map = [];

        foreach ($items as $item) {
            $metadata = $this->registry->getByClass($item::class);
            if ($metadata === null) {
                continue;
            }

            $id = $this->repository->propertyAccessor()->getValue($item, $metadata->idPropertyPath ?? 'id');
            $stringId = $this->stringifyId($id);
            if ($stringId === null) {
                continue;
            }

            $map[$metadata->type . ':' . $stringId] = $item;
        }

        return $map;
    }

    private function setRelationshipValue(object $model, RelationshipMetadata $relationship, mixed $value): void
    {
        $property = $relationship->propertyPath ?? $relationship->name;

        try {
            $reflection = new ReflectionClass($model);
            if ($reflection->hasProperty($property)) {
                $prop = $reflection->getProperty($property);
                $prop->setAccessible(true);
                $prop->setValue($model, $value);

                return;
            }
        } catch (ReflectionException) {
            // fallback to accessor below
        }

        $this->repository->propertyAccessor()->setValue($model, $property, $value);
    }

    private function stringifyId(mixed $value): ?string
    {
        if (is_int($value) || is_string($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        if (is_float($value)) {
            return (string) $value;
        }

        return null;
    }
}
