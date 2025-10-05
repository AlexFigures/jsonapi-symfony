<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Resource\Registry;

use JsonApi\Symfony\Resource\Attribute\Attribute as AttributeAttribute;
use JsonApi\Symfony\Resource\Attribute\Id;
use JsonApi\Symfony\Resource\Attribute\JsonApiResource;
use JsonApi\Symfony\Resource\Attribute\Relationship as RelationshipAttribute;
use JsonApi\Symfony\Resource\Metadata\AttributeMetadata;
use JsonApi\Symfony\Resource\Metadata\RelationshipMetadata;
use JsonApi\Symfony\Resource\Metadata\ResourceMetadata;
use LogicException;
use ReflectionAttribute;
use ReflectionClass;
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
     * @param iterable<object|string> $resources
     */
    public function __construct(iterable $resources)
    {
        foreach ($resources as $type => $resource) {
            $class = is_object($resource) ? $resource::class : (string) $resource;
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

    private function buildMetadata(string $class): ResourceMetadata
    {
        $reflection = new ReflectionClass($class);
        $resourceAttributes = $reflection->getAttributes(JsonApiResource::class, ReflectionAttribute::IS_INSTANCEOF);

        if ($resourceAttributes === []) {
            throw new LogicException(sprintf('Class %s is not marked with #[JsonApiResource].', $class));
        }

        /** @var JsonApiResource $resource */
        $resource = $resourceAttributes[0]->newInstance();

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

        return new ResourceMetadata(
            $resource->type,
            $class,
            $attributes,
            $relationships,
            $resource->exposeId,
            $idPropertyPath,
        );
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

            $attributes[$name] = new AttributeMetadata($name, $propertyPath, $instance->readable, $instance->writable);
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
            $relationships[$name] = new RelationshipMetadata(
                $name,
                $instance->toMany,
                $this->guessTargetType($targetClass),
                $propertyPath,
                $targetClass,
            );
        }

        return $relationships;
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
            return $type->isBuiltin() ? null : $type->getName();
        }

        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $inner) {
                if (!$inner->isBuiltin()) {
                    return $inner->getName();
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
