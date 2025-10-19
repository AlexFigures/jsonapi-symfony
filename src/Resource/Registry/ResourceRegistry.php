<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Resource\Registry;

use AlexFigures\Symfony\Resource\Attribute\Attribute as AttributeAttribute;
use AlexFigures\Symfony\Resource\Attribute\FilterableFields;
use AlexFigures\Symfony\Resource\Attribute\Id;
use AlexFigures\Symfony\Resource\Attribute\JsonApiResource;
use AlexFigures\Symfony\Resource\Attribute\Relationship as RelationshipAttribute;
use AlexFigures\Symfony\Resource\Attribute\SortableFields;
use AlexFigures\Symfony\Resource\Definition\VersionResolverInterface;
use AlexFigures\Symfony\Resource\Metadata\AttributeMetadata;
use AlexFigures\Symfony\Resource\Metadata\RelationshipLinkingPolicy;
use AlexFigures\Symfony\Resource\Metadata\RelationshipMetadata;
use AlexFigures\Symfony\Resource\Metadata\ResourceMetadata;
use LogicException;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;

final class ResourceRegistry implements ResourceRegistryInterface
{
    /**
     * @var array<string, ResourceMetadata>
     */
    private array $metadataByType = [];

    /**
     * @var array<string, ResourceMetadata>
     */
    private array $metadataByClass = [];

    /**
     * @var list<ResourceMetadata>
     */
    private array $metadata = [];

    /**
     * @param iterable<object|string> $resources
     */
    public function __construct(iterable $resources)
    {
        foreach ($resources as $type => $resource) {
            /** @var class-string $class */
            $class = is_object($resource) ? $resource::class : (string) $resource;
            if (!class_exists($class)) {
                throw new LogicException(sprintf('Resource class "%s" does not exist.', $class));
            }
            $metadata = $this->buildMetadata($class);

            if (is_string($type) && $type !== $metadata->type) {
                throw new LogicException(sprintf(
                    'Resource type mismatch for %s: expected "%s", got "%s".',
                    $class,
                    $type,
                    $metadata->type
                ));
            }

            if (isset($this->metadataByType[$metadata->type])) {
                throw new LogicException(sprintf('Resource type "%s" is already registered.', $metadata->type));
            }

            $this->metadataByType[$metadata->type] = $metadata;
            $this->metadataByClass[$metadata->class] = $metadata;
            $this->metadataByClass[$metadata->dataClass] = $metadata;
            if ($metadata->viewClass !== $metadata->class) {
                $this->metadataByClass[$metadata->viewClass] = $metadata;
            }
            $this->metadata[] = $metadata;
        }
    }

    public function getByType(string $type): ResourceMetadata
    {
        if (!isset($this->metadataByType[$type])) {
            throw new LogicException(sprintf('Unknown resource type "%s".', $type));
        }

        return $this->metadataByType[$type];
    }

    public function hasType(string $type): bool
    {
        return isset($this->metadataByType[$type]);
    }

    public function getByClass(string $class): ?ResourceMetadata
    {
        return $this->metadataByClass[$class] ?? null;
    }

    /**
     * @return list<ResourceMetadata>
     */
    public function all(): array
    {
        return $this->metadata;
    }

    /**
     * @param class-string $class
     */
    private function buildMetadata(string $class): ResourceMetadata
    {
        $reflection = new ReflectionClass($class);
        $resourceAttributes = $reflection->getAttributes(JsonApiResource::class, ReflectionAttribute::IS_INSTANCEOF);

        if ($resourceAttributes === []) {
            throw new LogicException(sprintf('Class %s is not marked with #[JsonApiResource].', $class));
        }

        /** @var JsonApiResource $resource */
        $resource = $resourceAttributes[0]->newInstance();

        $dataClass = $resource->dataClass === null ? $class : $this->assertClassString($resource->dataClass, sprintf('dataClass for resource %s', $class));
        $viewClass = $resource->viewClass === null ? $class : $this->assertClassString($resource->viewClass, sprintf('viewClass for resource %s', $class));

        $versionResolver = null;
        if ($resource->versionResolver !== null) {
            if (!is_a($resource->versionResolver, VersionResolverInterface::class, true)) {
                throw new LogicException(sprintf(
                    'Version resolver for resource %s must implement %s. Got "%s".',
                    $class,
                    VersionResolverInterface::class,
                    $resource->versionResolver,
                ));
            }

            $versionResolver = new ($resource->versionResolver)();
        }

        $attributes = [];
        $relationships = [];
        $idPropertyPath = null;

        foreach ($reflection->getProperties() as $property) {
            if ($this->hasAttribute($property, Id::class)) {
                $idPropertyPath = $property->getName();
            }

            $attributes = $this->extractAttributes($attributes, $property, $property->getName());
            $relationships = $this->extractRelationships($relationships, $property, $property->getName());
        }

        foreach ($reflection->getMethods() as $method) {
            if ($method->isStatic()) {
                continue;
            }

            $propertyPath = $this->guessPropertyPathFromMethod($method);
            if ($this->hasAttribute($method, Id::class)) {
                $idPropertyPath = $propertyPath;
            }

            $attributes = $this->extractAttributes($attributes, $method, $propertyPath);
            $relationships = $this->extractRelationships($relationships, $method, $propertyPath);
        }

        // Extract sortable fields from SortableFields attribute
        $sortableFields = [];
        $sortableFieldsAttributes = $reflection->getAttributes(SortableFields::class, ReflectionAttribute::IS_INSTANCEOF);
        if ($sortableFieldsAttributes !== []) {
            /** @var SortableFields $sortableFieldsAttr */
            $sortableFieldsAttr = $sortableFieldsAttributes[0]->newInstance();
            $sortableFields = $sortableFieldsAttr->fields;
        }

        // Extract filterable fields from FilterableFields attribute
        $filterableFields = null;
        $filterableFieldsAttributes = $reflection->getAttributes(FilterableFields::class, ReflectionAttribute::IS_INSTANCEOF);
        if ($filterableFieldsAttributes !== []) {
            /** @var FilterableFields $filterableFieldsAttr */
            $filterableFields = $filterableFieldsAttributes[0]->newInstance();
        }

        return new ResourceMetadata(
            type: $resource->type,
            class: $class,
            dataClass: $dataClass,
            viewClass: $viewClass,
            attributes: $attributes,
            relationships: $relationships,
            exposeId: $resource->exposeId,
            idPropertyPath: $idPropertyPath,
            routePrefix: $resource->routePrefix,
            description: $resource->description,
            sortableFields: $sortableFields,
            filterableFields: $filterableFields,
            operationGroups: null,
            normalizationContext: $resource->normalizationContext,
            denormalizationContext: $resource->denormalizationContext,
            readProjection: $resource->readProjection,
            fieldMap: $resource->fieldMap,
            relationshipPolicies: $resource->relationshipPolicies,
            writeRequests: $resource->writeRequests,
            versionResolver: $versionResolver,
        );
    }

    /**
     * @return class-string
     */
    private function assertClassString(string $candidate, string $context): string
    {
        if (class_exists($candidate) || interface_exists($candidate) || enum_exists($candidate)) {
            return $candidate;
        }

        throw new LogicException(sprintf('Class "%s" configured for %s does not exist.', $candidate, $context));
    }

    /**
     * @template T of ReflectionMethod|ReflectionProperty
     *
     * @param array<string, AttributeMetadata> $attributes
     * @param T                                $member
     *
     * @return array<string, AttributeMetadata>
     */
    private function extractAttributes(array $attributes, ReflectionProperty|ReflectionMethod $member, string $propertyPath): array
    {
        foreach ($member->getAttributes(AttributeAttribute::class, ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            /** @var AttributeAttribute $instance */
            $instance = $attribute->newInstance();
            $name = $instance->name ?? $this->guessName($member, $propertyPath);

            if (isset($attributes[$name])) {
                throw new LogicException(sprintf('Duplicate attribute "%s" detected on %s::%s.', $name, $member->getDeclaringClass()->getName(), $member->getName()));
            }

            [$types, $nullable] = $this->guessAttributeTypes($member);

            // Serialization groups are now controlled via Symfony's #[Groups] attribute
            // on the entity properties, not through SerializationGroups metadata

            $attributes[$name] = new AttributeMetadata(
                $name,
                $propertyPath,
                $types,
                $nullable,
            );
        }

        return $attributes;
    }

    /**
     * @template T of ReflectionMethod|ReflectionProperty
     *
     * @param array<string, RelationshipMetadata> $relationships
     * @param T                                   $member
     *
     * @return array<string, RelationshipMetadata>
     */
    private function extractRelationships(array $relationships, ReflectionProperty|ReflectionMethod $member, string $propertyPath): array
    {
        foreach ($member->getAttributes(RelationshipAttribute::class, ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            /** @var RelationshipAttribute $instance */
            $instance = $attribute->newInstance();
            $name = $this->guessName($member, $propertyPath);

            if (isset($relationships[$name])) {
                throw new LogicException(sprintf('Duplicate relationship "%s" detected on %s::%s.', $name, $member->getDeclaringClass()->getName(), $member->getName()));
            }

            $targetClass = $this->guessTargetClass($member);
            $nullable = true;
            if (!$instance->toMany) {
                $type = $member instanceof ReflectionProperty ? $member->getType() : $member->getReturnType();
                if ($type !== null) {
                    $nullable = $type->allowsNull();
                }
            }

            $targetType = $instance->targetType ?? $this->guessTargetType($targetClass);

            // Convert linkingPolicy from string to enum if needed
            $linkingPolicy = RelationshipLinkingPolicy::REFERENCE; // default
            if ($instance->linkingPolicy !== null) {
                $linkingPolicy = $instance->linkingPolicy instanceof RelationshipLinkingPolicy
                    ? $instance->linkingPolicy
                    : RelationshipLinkingPolicy::from($instance->linkingPolicy);
            }

            $relationships[$name] = new RelationshipMetadata(
                $name,
                $instance->toMany,
                $targetType,
                $propertyPath,
                $targetClass,
                $nullable,
                $linkingPolicy,
            );
        }

        return $relationships;
    }

    /**
     * @return array{0: list<string>, 1: bool}
     */
    private function guessAttributeTypes(ReflectionProperty|ReflectionMethod $member): array
    {
        $type = $member instanceof ReflectionProperty ? $member->getType() : $member->getReturnType();

        if ($type === null) {
            return [[], true];
        }

        $nullable = $type->allowsNull();
        $types = [];

        if ($type instanceof ReflectionNamedType) {
            if ($type->getName() !== 'null') {
                $types[] = $type->getName();
            }
        } elseif ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $inner) {
                if (!$inner instanceof ReflectionNamedType) {
                    continue;
                }

                if ($inner->getName() === 'null') {
                    $nullable = true;

                    continue;
                }

                $types[] = $inner->getName();
            }
        } elseif ($type instanceof ReflectionIntersectionType) {
            foreach ($type->getTypes() as $inner) {
                if ($inner instanceof ReflectionNamedType && !$inner->isBuiltin()) {
                    $types[] = $inner->getName();
                }
            }
        }

        return [$types, $nullable];
    }

    private function guessName(ReflectionProperty|ReflectionMethod $member, string $propertyPath): string
    {
        if ($member instanceof ReflectionProperty) {
            return $propertyPath;
        }

        $method = $member->getName();
        foreach (['get', 'is', 'has'] as $prefix) {
            if (str_starts_with($method, $prefix) && strlen($method) > strlen($prefix)) {
                return lcfirst(substr($method, strlen($prefix)));
            }
        }

        return $propertyPath;
    }

    private function guessPropertyPathFromMethod(ReflectionMethod $method): string
    {
        $name = $method->getName();
        foreach (['get', 'is', 'has'] as $prefix) {
            if (str_starts_with($name, $prefix) && strlen($name) > strlen($prefix)) {
                return lcfirst(substr($name, strlen($prefix)));
            }
        }

        return $name;
    }

    private function hasAttribute(ReflectionProperty|ReflectionMethod $member, string $attribute): bool
    {
        return $member->getAttributes($attribute, ReflectionAttribute::IS_INSTANCEOF) !== [];
    }

    private function guessTargetClass(ReflectionProperty|ReflectionMethod $member): ?string
    {
        $type = $member instanceof ReflectionProperty ? $member->getType() : $member->getReturnType();

        if ($type === null) {
            return null;
        }

        if ($type instanceof ReflectionNamedType) {
            if ($type->isBuiltin()) {
                return null;
            }

            $className = $type->getName();

            // Resolve "self", "static", and "parent" to actual class names
            if ($className === 'self' || $className === 'static') {
                return $member->getDeclaringClass()->getName();
            }

            if ($className === 'parent') {
                $parentClass = $member->getDeclaringClass()->getParentClass();
                return $parentClass ? $parentClass->getName() : null;
            }

            return $className;
        }

        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $inner) {
                if ($inner instanceof ReflectionNamedType && !$inner->isBuiltin()) {
                    $className = $inner->getName();

                    // Resolve "self", "static", and "parent" to actual class names
                    if ($className === 'self' || $className === 'static') {
                        return $member->getDeclaringClass()->getName();
                    }

                    if ($className === 'parent') {
                        $parentClass = $member->getDeclaringClass()->getParentClass();
                        return $parentClass ? $parentClass->getName() : null;
                    }

                    return $className;
                }
            }
        }

        if ($type instanceof ReflectionIntersectionType) {
            foreach ($type->getTypes() as $inner) {
                if ($inner instanceof ReflectionNamedType && !$inner->isBuiltin()) {
                    $className = $inner->getName();

                    // Resolve "self", "static", and "parent" to actual class names
                    if ($className === 'self' || $className === 'static') {
                        return $member->getDeclaringClass()->getName();
                    }

                    if ($className === 'parent') {
                        $parentClass = $member->getDeclaringClass()->getParentClass();
                        return $parentClass ? $parentClass->getName() : null;
                    }

                    return $className;
                }
            }
        }

        return null;
    }

    private function guessTargetType(?string $targetClass): ?string
    {
        if ($targetClass === null || !class_exists($targetClass)) {
            return null;
        }

        if (isset($this->metadataByClass[$targetClass])) {
            return $this->metadataByClass[$targetClass]->type;
        }

        $reflection = new ReflectionClass($targetClass);
        $attributes = $reflection->getAttributes(JsonApiResource::class, ReflectionAttribute::IS_INSTANCEOF);

        if ($attributes === []) {
            return null;
        }

        /** @var JsonApiResource $attribute */
        $attribute = $attributes[0]->newInstance();

        return $attribute->type;
    }
}
