<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Document;

use JsonApi\Symfony\Contract\Data\Slice;
use JsonApi\Symfony\Http\Link\LinkGenerator;
use JsonApi\Symfony\Query\Criteria;
use JsonApi\Symfony\Resource\Metadata\AttributeMetadata;
use JsonApi\Symfony\Resource\Metadata\RelationshipMetadata;
use JsonApi\Symfony\Resource\Metadata\ResourceMetadata;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;
use stdClass;
use Stringable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

final class DocumentBuilder
{
    public function __construct(
        private readonly ResourceRegistryInterface $registry,
        private readonly PropertyAccessorInterface $accessor,
        private readonly LinkGenerator $links,
    ) {
    }

    /**
     * @param list<object> $models
     */
    public function buildCollection(string $type, array $models, Criteria $criteria, Slice $slice, Request $request): array
    {
        $data = [];
        $included = [];
        $visited = [];
        $includeTree = $this->buildIncludeTree($criteria);

        foreach ($models as $model) {
            $data[] = $this->buildResourceObject($type, $model, $criteria);
            if ($includeTree !== []) {
                $this->gatherIncluded($type, $model, $includeTree, $criteria, $included, $visited);
            }
        }

        $document = [
            'jsonapi' => ['version' => '1.1'],
            'links' => array_merge(
                ['self' => $this->links->topLevelSelf($request)],
                $this->links->collectionPagination($type, $criteria->pagination, $slice->totalItems, $request),
            ),
            'data' => $data,
            'meta' => [
                'total' => $slice->totalItems,
                'page' => $slice->pageNumber,
                'size' => $slice->pageSize,
            ],
        ];

        if ($included !== []) {
            $document['included'] = array_values($included);
        }

        return $document;
    }

    public function buildResource(string $type, object $model, Criteria $criteria, Request $request): array
    {
        $includeTree = $this->buildIncludeTree($criteria);
        $included = [];
        $visited = [];

        if ($includeTree !== []) {
            $this->gatherIncluded($type, $model, $includeTree, $criteria, $included, $visited);
        }

        $document = [
            'jsonapi' => ['version' => '1.1'],
            'links' => ['self' => $this->links->topLevelSelf($request)],
            'data' => $this->buildResourceObject($type, $model, $criteria),
        ];

        if ($included !== []) {
            $document['included'] = array_values($included);
        }

        return $document;
    }

    private function buildResourceObject(string $type, object $model, Criteria $criteria): array
    {
        $metadata = $this->registry->getByType($type);
        $fields = $criteria->fields[$type] ?? null;
        $attributes = $this->buildAttributes($metadata, $model, $fields);
        $id = $this->resolveId($metadata, $model);

        $resource = [
            'type' => $type,
            'id' => $id,
            'links' => [
                'self' => $this->links->resourceSelf($type, $id),
            ],
        ];

        $resource['attributes'] = $attributes === [] ? new stdClass() : $attributes;

        return $resource;
    }

    /**
     * @param list<string>|null $fields
     *
     * @return array<string, mixed>
     */
    private function buildAttributes(ResourceMetadata $metadata, object $model, ?array $fields): array
    {
        $attributes = [];
        $restrict = $fields !== null;

        /** @var AttributeMetadata $attribute */
        foreach ($metadata->attributes as $name => $attribute) {
            if (!$attribute->readable) {
                continue;
            }

            if ($restrict && !in_array($name, $fields, true)) {
                continue;
            }

            $value = $this->accessor->getValue($model, $attribute->propertyPath ?? $name);
            $attributes[$name] = $this->normalizeAttributeValue($value);
        }

        return $attributes;
    }

    /**
     * @param array<string, array<string, mixed>> $includeTree
     * @param array<string, array<string, mixed>> $included
     * @param array<string, bool>                 $visited
     */
    private function gatherIncluded(string $type, object $model, array $includeTree, Criteria $criteria, array &$included, array &$visited): void
    {
        if ($includeTree === []) {
            return;
        }

        $metadata = $this->registry->getByType($type);

        foreach ($includeTree as $relationshipName => $children) {
            if (!isset($metadata->relationships[$relationshipName])) {
                continue;
            }

            /** @var RelationshipMetadata $relationship */
            $relationship = $metadata->relationships[$relationshipName];
            $propertyPath = $relationship->propertyPath ?? $relationshipName;
            $related = $this->accessor->getValue($model, $propertyPath);

            if ($related === null) {
                continue;
            }

            $relatedItems = $relationship->toMany ? $this->normalizeToMany($related) : [$related];

            foreach ($relatedItems as $relatedItem) {
                if (!is_object($relatedItem)) {
                    continue;
                }

                $relatedType = $relationship->targetType;
                if ($relatedType === null) {
                    $metadataForClass = $this->registry->getByClass($relatedItem::class);
                    if ($metadataForClass === null) {
                        continue;
                    }

                    $relatedType = $metadataForClass->type;
                }

                $resource = $this->buildResourceObject($relatedType, $relatedItem, $criteria);
                $identifier = $resource['type'] . ':' . $resource['id'];

                if (!isset($visited[$identifier])) {
                    $visited[$identifier] = true;
                    $included[$identifier] = $resource;
                    if ($children !== []) {
                        $this->gatherIncluded($relatedType, $relatedItem, $children, $criteria, $included, $visited);
                    }
                } elseif ($children !== []) {
                    $this->gatherIncluded($relatedType, $relatedItem, $children, $criteria, $included, $visited);
                }
            }
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function buildIncludeTree(Criteria $criteria): array
    {
        $tree = [];

        foreach ($criteria->include as $path) {
            $segments = explode('.', $path);
            $cursor = &$tree;
            foreach ($segments as $segment) {
                if (!isset($cursor[$segment])) {
                    $cursor[$segment] = [];
                }
                $cursor = &$cursor[$segment];
            }
            unset($cursor);
        }

        return $tree;
    }

    private function resolveId(ResourceMetadata $metadata, object $model): string
    {
        $propertyPath = $metadata->idPropertyPath ?? 'id';
        $value = $this->accessor->getValue($model, $propertyPath);

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            return $value;
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        throw new \RuntimeException(sprintf('Unable to resolve resource identifier for %s.', $metadata->class));
    }

    private function normalizeAttributeValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        return $value;
    }

    /**
     * @return list<object>
     */
    private function normalizeToMany(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, static fn ($item) => $item !== null));
        }

        if ($value instanceof \Traversable) {
            $result = [];
            foreach ($value as $item) {
                if ($item !== null) {
                    $result[] = $item;
                }
            }

            return $result;
        }

        return [];
    }
}
