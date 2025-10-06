<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Fixtures\InMemory;

use DateInterval;
use DateTimeImmutable;
use JsonApi\Symfony\Contract\Data\ResourceIdentifier;
use JsonApi\Symfony\Contract\Data\ResourceRepository;
use JsonApi\Symfony\Contract\Data\Slice;
use JsonApi\Symfony\Query\Criteria;
use JsonApi\Symfony\Query\Sorting;
use JsonApi\Symfony\Resource\Metadata\AttributeMetadata;
use JsonApi\Symfony\Resource\Metadata\ResourceMetadata;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;
use JsonApi\Symfony\Tests\Fixtures\Model\Article;
use JsonApi\Symfony\Tests\Fixtures\Model\Author;
use JsonApi\Symfony\Tests\Fixtures\Model\Tag;
use ReflectionClass;
use RuntimeException;
use Stringable;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

final class InMemoryRepository implements ResourceRepository
{
    private PropertyAccessorInterface $accessor;

    /**
     * @var array<string, array<int, object>>
     */
    private array $data = [];

    public function __construct(
        private readonly ResourceRegistryInterface $registry,
        ?PropertyAccessorInterface $accessor = null,
    ) {
        $this->accessor = $accessor ?? PropertyAccess::createPropertyAccessor();
        $this->seed();
    }

    public function has(string $type, string $id): bool
    {
        return $this->findModel($type, $id) !== null;
    }

    public function get(string $type, string $id): ?object
    {
        return $this->findModel($type, $id);
    }

    public function save(string $type, object $model): void
    {
        $metadata = $this->registry->getByType($type);
        $path = $metadata->idPropertyPath ?? 'id';
        $idValue = $this->accessor->getValue($model, $path);
        $id = $this->stringifyId($idValue);
        if ($id === null) {
            throw new RuntimeException(sprintf('Unable to determine identifier for %s.', $type));
        }

        $this->data[$type] ??= [];

        foreach ($this->data[$type] as $index => $existing) {
            $existingId = $this->accessor->getValue($existing, $path);
            $existingIdString = $this->stringifyId($existingId);
            if ($existingIdString !== null && $existingIdString === $id) {
                $this->data[$type][$index] = $model;

                return;
            }
        }

        $this->data[$type][] = $model;
    }

    public function remove(string $type, string $id): void
    {
        if (!isset($this->data[$type])) {
            return;
        }

        $metadata = $this->registry->getByType($type);
        $path = $metadata->idPropertyPath ?? 'id';

        foreach ($this->data[$type] as $index => $existing) {
            $existingId = $this->accessor->getValue($existing, $path);
            $existingIdString = $this->stringifyId($existingId);
            if ($existingIdString !== null && $existingIdString === $id) {
                unset($this->data[$type][$index]);
                $this->data[$type] = array_values($this->data[$type]);

                return;
            }
        }
    }

    public function createPrototype(string $type): object
    {
        $metadata = $this->registry->getByType($type);
        $reflection = new ReflectionClass($metadata->class);

        // Special handling for Article which requires constructor parameters
        if ($metadata->class === Article::class) {
            // Create with minimal required parameters
            $dummyAuthor = new Author('temp-author-id', 'Temp Author');
            return new Article('temp-id', 'Temp Title', new DateTimeImmutable(), $dummyAuthor);
        }

        // For other types, try to clone existing or create without constructor
        if (isset($this->data[$type]) && $this->data[$type] !== []) {
            return clone $this->data[$type][0];
        }

        return $reflection->newInstanceWithoutConstructor();
    }

    public function findCollection(string $type, Criteria $criteria): Slice
    {
        $items = array_values($this->data[$type] ?? []);
        $items = $this->applySort($type, $items, $criteria->sort);

        $total = count($items);
        $size = $criteria->pagination->size;
        $number = $criteria->pagination->number;
        $offset = max(0, ($number - 1) * $size);

        $items = array_slice($items, $offset, $size);

        return new Slice($items, $number, $size, $total);
    }

    public function findOne(string $type, string $id, Criteria $criteria): ?object
    {
        return $this->findModel($type, $id);
    }

    public function findRelated(string $type, string $relationship, array $identifiers): iterable
    {
        $results = [];

        foreach ($identifiers as $identifier) {
            $model = $this->findModel($identifier->type, $identifier->id);
            if ($model !== null) {
                $results[] = $model;
            }
        }

        return $results;
    }

    public function count(string $type): int
    {
        return count($this->data[$type] ?? []);
    }

    /**
     * @param list<object>  $items
     * @param list<Sorting> $sorting
     *
     * @return list<object>
     */
    private function applySort(string $type, array $items, array $sorting): array
    {
        if ($sorting === []) {
            return $items;
        }

        $metadata = $this->registry->getByType($type);

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
            /** @var AttributeMetadata $attribute */
            $attribute = $metadata->attributes[$field];

            return $attribute->propertyPath ?? $field;
        }

        throw new RuntimeException(sprintf('Unknown sorting field "%s" for %s.', $field, $metadata->type));
    }

    private function normalizeSortable(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }

        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        return $value;
    }

    private function seed(): void
    {
        $authors = [
            new Author('1', 'Alice'),
            new Author('2', 'Bob'),
            new Author('3', 'Carol'),
        ];

        $tags = [
            new Tag('1', 'php'),
            new Tag('2', 'symfony'),
            new Tag('3', 'jsonapi'),
            new Tag('4', 'dx'),
            new Tag('5', 'rest'),
        ];

        $articles = [];
        $date = new DateTimeImmutable('2024-01-01T10:00:00Z');
        for ($i = 1; $i <= 15; ++$i) {
            $author = $authors[($i - 1) % count($authors)];
            $articleTags = [$tags[($i - 1) % count($tags)], $tags[$i % count($tags)]];
            $articles[] = new Article((string) $i, sprintf('Article %02d', $i), $date, $author, ...$articleTags);
            $date = $date->add(new DateInterval('P1D'));
        }

        $this->data = [
            'articles' => $articles,
            'authors' => $authors,
            'tags' => $tags,
        ];
    }

    private function findModel(string $type, string $id): ?object
    {
        if (!isset($this->data[$type])) {
            return null;
        }

        $metadata = $this->registry->getByType($type);
        $path = $metadata->idPropertyPath ?? 'id';

        foreach ($this->data[$type] as $model) {
            $value = $this->accessor->getValue($model, $path);
            $stringValue = $this->stringifyId($value);
            if ($stringValue !== null && $stringValue === $id) {
                return $model;
            }
        }

        return null;
    }

    public function propertyAccessor(): PropertyAccessorInterface
    {
        return $this->accessor;
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
