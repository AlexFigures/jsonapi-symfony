<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Fixtures\InMemory;

use AlexFigures\Symfony\Contract\Data\RelationshipReader;
use AlexFigures\Symfony\Contract\Data\Slice;
use AlexFigures\Symfony\Contract\Data\SliceIds;
use AlexFigures\Symfony\Http\Exception\NotFoundException;
use AlexFigures\Symfony\Query\Criteria;
use AlexFigures\Symfony\Query\Pagination;
use AlexFigures\Symfony\Query\Sorting;
use AlexFigures\Symfony\Resource\Metadata\RelationshipMetadata;
use AlexFigures\Symfony\Resource\Metadata\ResourceMetadata;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
use Stringable;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

final class InMemoryRelationshipReader implements RelationshipReader
{
    private PropertyAccessorInterface $accessor;

    public function __construct(
        private readonly ResourceRegistryInterface $registry,
        private readonly InMemoryRepository $repository,
        ?PropertyAccessorInterface $accessor = null,
    ) {
        $this->accessor = $accessor ?? PropertyAccess::createPropertyAccessor();
    }

    public function getToOneId(string $type, string $id, string $rel): ?string
    {
        $model = $this->requireModel($type, $id);
        $relationship = $this->requireRelationship($type, $rel);
        $related = $this->accessor->getValue($model, $relationship->propertyPath ?? $rel);

        if ($related === null || !is_object($related)) {
            return null;
        }

        $targetMetadata = $this->resolveTargetMetadata($relationship, $related);
        if ($targetMetadata === null) {
            return null;
        }

        return $this->resolveId($targetMetadata, $related);
    }

    public function getToManyIds(string $type, string $id, string $rel, Pagination $pagination): SliceIds
    {
        $model = $this->requireModel($type, $id);
        $relationship = $this->requireRelationship($type, $rel);
        $items = $this->getRelatedItems($model, $relationship);
        $total = count($items);
        $offset = max(0, ($pagination->number - 1) * $pagination->size);
        $chunk = array_slice($items, $offset, $pagination->size);

        $ids = [];
        foreach ($chunk as $item) {
            $targetMetadata = $this->resolveTargetMetadata($relationship, $item);
            if ($targetMetadata === null) {
                continue;
            }

            $ids[] = $this->resolveId($targetMetadata, $item);
        }

        return new SliceIds($ids, $pagination->number, $pagination->size, $total);
    }

    public function getRelatedResource(string $type, string $id, string $rel): ?object
    {
        $model = $this->requireModel($type, $id);
        $relationship = $this->requireRelationship($type, $rel);
        $related = $this->accessor->getValue($model, $relationship->propertyPath ?? $rel);

        return is_object($related) ? $related : null;
    }

    public function getRelatedCollection(string $type, string $id, string $rel, Criteria $criteria): Slice
    {
        $model = $this->requireModel($type, $id);
        $relationship = $this->requireRelationship($type, $rel);
        $items = $this->getRelatedItems($model, $relationship);
        $items = $this->applySort($relationship, $items, $criteria->sort);

        $total = count($items);
        $size = $criteria->pagination->size;
        $number = $criteria->pagination->number;
        $offset = max(0, ($number - 1) * $size);
        $items = array_slice($items, $offset, $size);

        return new Slice($items, $number, $size, $total);
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
    private function getRelatedItems(object $model, RelationshipMetadata $relationship): array
    {
        $value = $this->accessor->getValue($model, $relationship->propertyPath ?? $relationship->name);

        if ($value === null) {
            return [];
        }

        if (!$relationship->toMany) {
            return is_object($value) ? [$value] : [];
        }

        if (is_array($value)) {
            $items = [];
            foreach ($value as $item) {
                if ($item !== null && is_object($item)) {
                    $items[] = $item;
                }
            }

            return $items;
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
     * @param list<object>  $items
     * @param list<Sorting> $sorting
     *
     * @return list<object>
     */
    private function applySort(RelationshipMetadata $relationship, array $items, array $sorting): array
    {
        if ($sorting === [] || $items === []) {
            return $items;
        }

        $metadata = $this->resolveCollectionMetadata($relationship, $items);
        if ($metadata === null) {
            return $items;
        }

        usort($items, function (object $a, object $b) use ($sorting, $metadata): int {
            foreach ($sorting as $sort) {
                $result = $this->compare($metadata, $a, $b, $sort);
                if ($result !== 0) {
                    return $sort->desc ? -$result : $result;
                }
            }

            return 0;
        });

        return $items;
    }

    private function compare(ResourceMetadata $metadata, object $a, object $b, Sorting $sorting): int
    {
        $path = $this->propertyPathForSort($metadata, $sorting->field);
        $left = $this->accessor->getValue($a, $path);
        $right = $this->accessor->getValue($b, $path);

        $left = $this->normalizeSortable($left);
        $right = $this->normalizeSortable($right);

        return $left <=> $right;
    }

    private function propertyPathForSort(ResourceMetadata $metadata, string $field): string
    {
        if ($field === 'id') {
            return $metadata->idPropertyPath ?? 'id';
        }

        if (isset($metadata->attributes[$field])) {
            return $metadata->attributes[$field]->propertyPath ?? $field;
        }

        return $field;
    }

    private function normalizeSortable(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        return $value;
    }

    private function resolveTargetMetadata(RelationshipMetadata $relationship, object $model): ?ResourceMetadata
    {
        if ($relationship->targetType !== null) {
            return $this->registry->getByType($relationship->targetType);
        }

        return $this->registry->getByClass($model::class);
    }

    /**
     * @param list<object> $items
     */
    private function resolveCollectionMetadata(RelationshipMetadata $relationship, array $items): ?ResourceMetadata
    {
        if ($relationship->targetType !== null) {
            return $this->registry->getByType($relationship->targetType);
        }

        $first = $items[0] ?? null;
        if ($first === null) {
            return null;
        }

        return $this->registry->getByClass($first::class);
    }

    private function resolveId(ResourceMetadata $metadata, object $model): string
    {
        $path = $metadata->idPropertyPath ?? 'id';
        $value = $this->accessor->getValue($model, $path);

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            return $value;
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        throw new \RuntimeException(sprintf('Unable to resolve identifier for "%s".', $metadata->type));
    }
}
