<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Document;

use AlexFigures\Symfony\Contract\Data\Slice;
use AlexFigures\Symfony\Http\Link\LinkGenerator;
use AlexFigures\Symfony\Http\Safety\LimitsEnforcer;
use AlexFigures\Symfony\Profile\ProfileContext;
use AlexFigures\Symfony\Query\Criteria;
use AlexFigures\Symfony\Resource\Metadata\AttributeMetadata;
use AlexFigures\Symfony\Resource\Metadata\RelationshipMetadata;
use AlexFigures\Symfony\Resource\Metadata\ResourceMetadata;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
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
        private readonly string $relationshipLinkageMode = 'when_included',
        private readonly ?LimitsEnforcer $limits = null,
    ) {
    }

    /**
     * @param list<object> $models
     *
     * @return array{
     *     jsonapi: array{version: string},
     *     links: array<string, string|list<string>>,
     *     data: list<array<string, mixed>>,
     *     meta: array<string, mixed>,
     *     included?: list<array<string, mixed>>
     * }
     */
    public function buildCollection(string $type, array $models, Criteria $criteria, Slice $slice, Request $request): array
    {
        $data = [];
        $included = [];
        $visited = [];
        $includeTree = $this->buildIncludeTree($criteria);
        $context = ProfileContext::fromRequest($request);

        foreach ($models as $model) {
            $data[] = $this->buildResourceObject($type, $model, $criteria, $context, $includeTree);
            if ($includeTree !== []) {
                $this->gatherIncluded($type, $model, $includeTree, $criteria, $included, $visited, $context);
            }
        }

        /** @var array<string, string|list<string>> $links */
        $links = array_merge(
            ['self' => $this->links->topLevelSelf($request)],
            $this->links->collectionPagination($type, $criteria->pagination, $slice->totalItems, $request),
        );
        /** @var array<string, mixed> $meta */
        $meta = [
            'total' => $slice->totalItems,
            'page' => $slice->pageNumber,
            'size' => $slice->pageSize,
        ];

        if ($context !== null) {
            $links = $this->applyTopLevelLinks($context, $links, $request);
            $updatedMeta = $this->applyTopLevelMeta($context, $meta);
            if ($updatedMeta !== []) {
                $meta = $updatedMeta;
            }
        }

        /**
         * @var array{
         *     jsonapi: array{version: '1.1'},
         *     links: array<string, string|list<string>>,
         *     data: list<array<string, mixed>>,
         *     meta: array<string, mixed>,
         *     included?: list<array<string, mixed>>
         * }
         */
        $document = [
            'jsonapi' => ['version' => '1.1'],
            'links' => $links,
            'data' => $data,
            'meta' => $meta,
        ];

        if ($included !== []) {
            $this->limits?->assertIncludedCount(count($included));
            $document['included'] = array_values($included);
        }

        return $document;
    }

    /**
     * @return array{
     *     jsonapi: array{version: string},
     *     links: array<string, string|list<string>>,
     *     data: array{
     *         type: string,
     *         id: string,
     *         links: array<string, string>,
     *         attributes: array<string, mixed>|stdClass,
     *         relationships?: array<string, array<string, mixed>>
     *     },
     *     meta?: array<string, mixed>,
     *     included?: list<array<string, mixed>>
     * }
     */
    public function buildResource(string $type, object $model, Criteria $criteria, Request $request): array
    {
        $includeTree = $this->buildIncludeTree($criteria);
        $included = [];
        $visited = [];
        $context = ProfileContext::fromRequest($request);

        if ($includeTree !== []) {
            $this->gatherIncluded($type, $model, $includeTree, $criteria, $included, $visited, $context);
        }

        /** @var array<string, string|list<string>> $links */
        $links = ['self' => $this->links->topLevelSelf($request)];
        /** @var array<string, mixed> $meta */
        $meta = [];

        if ($context !== null) {
            $links = $this->applyTopLevelLinks($context, $links, $request);
            $meta = $this->applyTopLevelMeta($context, $meta);
        }

        $document = [
            'jsonapi' => ['version' => '1.1'],
            'links' => $links,
            'data' => $this->buildResourceObject($type, $model, $criteria, $context, $includeTree),
        ];

        if ($included !== []) {
            $this->limits?->assertIncludedCount(count($included));
            $document['included'] = array_values($included);
        }

        if ($context !== null && $meta !== []) {
            $document['meta'] = $meta;
        }

        return $document;
    }

    /**
     * @param array<string, mixed> $activeIncludeTree Local include tree for this resource (used for nested includes)
     *
     * @return array{
     *     type: string,
     *     id: string,
     *     links: array<string, string>,
     *     attributes: array<string, mixed>|stdClass,
     *     relationships?: array<string, array<string, mixed>>
     * }
     */
    private function buildResourceObject(string $type, object $model, Criteria $criteria, ?ProfileContext $context = null, array $activeIncludeTree = []): array
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

        $relationships = $this->buildRelationships($metadata, $model, $criteria, $id, $context, $activeIncludeTree);
        if ($relationships !== []) {
            $resource['relationships'] = $relationships;
        }

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
        $normalizationGroups = $metadata->getNormalizationGroups();

        /** @var AttributeMetadata $attribute */
        foreach ($metadata->attributes as $name => $attribute) {
            // Check if attribute is in normalization groups (if groups are defined)
            if (!empty($normalizationGroups)) {
                $propertyPath = $attribute->propertyPath ?? $name;
                if (!$this->isAttributeInGroups($model, $propertyPath, $normalizationGroups)) {
                    continue;
                }
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
     * Check if an attribute is in the specified serialization groups.
     *
     * @param list<string> $groups
     */
    private function isAttributeInGroups(object $model, string $property, array $groups): bool
    {
        if (empty($groups)) {
            return true; // If no groups defined, show all attributes
        }

        try {
            $reflection = new \ReflectionClass($model);
            if (!$reflection->hasProperty($property)) {
                return true; // If property doesn't exist, show it (might be a getter)
            }

            $reflectionProperty = $reflection->getProperty($property);
            $groupsAttributes = $reflectionProperty->getAttributes(\Symfony\Component\Serializer\Annotation\Groups::class);

            if (empty($groupsAttributes)) {
                return true; // If no groups defined on property, show it
            }

            /** @var \Symfony\Component\Serializer\Annotation\Groups $propertyGroups */
            $propertyGroups = $groupsAttributes[0]->newInstance();

            // Check if there's an intersection between property groups and requested groups
            return !empty(array_intersect($groups, $propertyGroups->getGroups()));
        } catch (\ReflectionException $e) {
            return true; // On error, show the attribute
        }
    }

    /**
     * @param array<string, mixed> $activeIncludeTree Local include tree for this resource
     *
     * @return array<string, array<string, mixed>>
     */
    private function buildRelationships(ResourceMetadata $metadata, object $model, Criteria $criteria, string $id, ?ProfileContext $context, array $activeIncludeTree = []): array
    {
        $relationships = [];
        $fields = $criteria->fields[$metadata->type] ?? null;
        $restrict = $fields !== null;

        foreach ($metadata->relationships as $name => $relationship) {
            if ($restrict && !in_array($name, $fields, true)) {
                continue;
            }

            $data = [
                'links' => [
                    'self' => $this->links->relationshipSelf($metadata->type, $id, $name),
                    'related' => $this->links->relationshipRelated($metadata->type, $id, $name),
                ],
            ];

            if ($this->shouldIncludeRelationshipData($criteria, $metadata->type, $name, $activeIncludeTree)) {
                $linkage = $this->resolveRelationshipLinkage($relationship, $model);
                $data['data'] = $linkage;
            }

            $relationships[$name] = $data;
        }

        if ($relationships !== [] && $context !== null) {
            foreach ($context->documentHooks() as $hook) {
                $hook->onResourceRelationships($context, $metadata, $relationships, $model);
            }
        }

        return $relationships;
    }

    /**
     * @param array<string, mixed> $activeIncludeTree Local include tree for this resource
     */
    private function shouldIncludeRelationshipData(Criteria $criteria, string $type, string $relationship, array $activeIncludeTree = []): bool
    {
        return match ($this->relationshipLinkageMode) {
            'always' => true,
            'never' => false,
            default => $this->isRelationshipRequested($criteria, $type, $relationship, $activeIncludeTree),
        };
    }

    /**
     * @param array<string, mixed> $activeIncludeTree Local include tree for this resource
     */
    private function isRelationshipRequested(Criteria $criteria, string $type, string $relationship, array $activeIncludeTree = []): bool
    {
        // First check if this relationship is in the local include tree (for nested includes)
        if (isset($activeIncludeTree[$relationship])) {
            return true;
        }

        $fields = $criteria->fields[$type] ?? null;
        if ($fields !== null && in_array($relationship, $fields, true)) {
            return true;
        }

        foreach ($criteria->include as $path) {
            if ($path === $relationship || str_starts_with($path, $relationship . '.')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{type: string, id: string}|list<array{type: string, id: string}>|null
     */
    private function resolveRelationshipLinkage(RelationshipMetadata $relationship, object $model): array|null
    {
        $propertyPath = $relationship->propertyPath ?? $relationship->name;
        $related = $this->accessor->getValue($model, $propertyPath);

        if ($relationship->toMany) {
            if ($related === null) {
                return [];
            }

            $items = $this->normalizeToMany($related);
            $identifiers = [];

            foreach ($items as $item) {
                $identifier = $this->buildIdentifier($relationship, $item);
                if ($identifier !== null) {
                    $identifiers[] = $identifier;
                }
            }

            return $identifiers;
        }

        if ($related === null || !is_object($related)) {
            return null;
        }

        return $this->buildIdentifier($relationship, $related);
    }

    /**
     * @return array{type: string, id: string}|null
     */
    private function buildIdentifier(RelationshipMetadata $relationship, object $related): ?array
    {
        $targetType = $relationship->targetType;

        if ($targetType === null) {
            $targetMetadata = $this->registry->getByClass($related::class);
            if ($targetMetadata === null) {
                return null;
            }

            $targetType = $targetMetadata->type;
        }

        $targetMetadata = $this->registry->getByType($targetType);
        $id = $this->resolveId($targetMetadata, $related);

        return ['type' => $targetType, 'id' => $id];
    }

    /**
     * @param array<string, mixed>                $includeTree
     * @param array<string, array<string, mixed>> $included
     * @param array<string, bool>                 $visited
     */
    private function gatherIncluded(string $type, object $model, array $includeTree, Criteria $criteria, array &$included, array &$visited, ?ProfileContext $context): void
    {
        if ($includeTree === []) {
            return;
        }

        $metadata = $this->registry->getByType($type);

        foreach ($includeTree as $relationshipName => $children) {
            if (!is_array($children)) {
                continue;
            }

            /** @var array<string, mixed> $children */
            $children = $children;

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

                $resource = $this->buildResourceObject($relatedType, $relatedItem, $criteria, $context, $children);
                $typeValue = $resource['type'];
                $idValue = $resource['id'];

                if ($typeValue === '' || $idValue === '') {
                    continue;
                }

                $identifier = $typeValue . ':' . $idValue;

                if (!isset($visited[$identifier])) {
                    $visited[$identifier] = true;
                    $included[$identifier] = $resource;
                    if ($children !== []) {
                        $this->gatherIncluded($relatedType, $relatedItem, $children, $criteria, $included, $visited, $context);
                    }
                } elseif ($children !== []) {
                    $this->gatherIncluded($relatedType, $relatedItem, $children, $criteria, $included, $visited, $context);
                }
            }
        }
    }

    /**
     * @param array<string, string|list<string>> $links
     *
     * @return array<string, string|list<string>>
     */
    private function applyTopLevelLinks(ProfileContext $context, array $links, Request $request): array
    {
        foreach ($context->documentHooks() as $hook) {
            $hook->onTopLevelLinks($context, $links, $request);
        }

        return $links;
    }

    /**
     * @param array<string, mixed> $meta
     *
     * @return array<string, mixed>
     */
    private function applyTopLevelMeta(ProfileContext $context, array $meta): array
    {
        foreach ($context->documentHooks() as $hook) {
            $hook->onTopLevelMeta($context, $meta);
        }

        return $meta;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildIncludeTree(Criteria $criteria): array
    {
        $tree = [];

        foreach ($criteria->include as $path) {
            $segments = explode('.', $path);
            $cursor = &$tree;
            foreach ($segments as $segment) {
                if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
                    $cursor[$segment] = [];
                }

                /** @var array<string, mixed> $next */
                $next = &$cursor[$segment];
                $cursor = &$next;
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
            return $value->format(\DATE_ATOM);
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
            return array_values(array_filter($value, static fn ($item) => is_object($item)));
        }

        if ($value instanceof \Traversable) {
            $result = [];
            foreach ($value as $item) {
                if (is_object($item)) {
                    $result[] = $item;
                }
            }

            return $result;
        }

        return [];
    }
}
