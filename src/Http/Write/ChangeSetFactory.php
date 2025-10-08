<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Write;

use JsonApi\Symfony\Contract\Data\ChangeSet;
use JsonApi\Symfony\Http\Exception\BadRequestException;
use JsonApi\Symfony\Resource\Metadata\AttributeMetadata;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;

final class ChangeSetFactory
{
    public function __construct(private readonly ResourceRegistryInterface $registry)
    {
    }

    /**
     * Creates a ChangeSet from JSON:API input data (attributes and relationships).
     *
     * This is the recommended method for creating ChangeSets as it populates both
     * attributes and relationships, providing a unified data flow.
     *
     * @param string $type Resource type
     * @param array<string, mixed> $attributes Attribute name => value map
     * @param array<string, array{data: mixed}> $relationships Relationship name => JSON:API relationship data
     * @return ChangeSet
     * @throws BadRequestException If unknown attributes are provided
     */
    public function fromInput(string $type, array $attributes, array $relationships = []): ChangeSet
    {
        $metadata = $this->registry->getByType($type);
        $mappedAttributes = [];

        foreach ($attributes as $name => $value) {
            if (!isset($metadata->attributes[$name])) {
                throw new BadRequestException(sprintf('Unknown attribute "%s" for type "%s".', $name, $type));
            }

            /** @var AttributeMetadata $attribute */
            $attribute = $metadata->attributes[$name];
            $path = $attribute->propertyPath ?? $name;
            $mappedAttributes[$path] = $value;
        }

        return new ChangeSet($mappedAttributes, $relationships);
    }

    /**
     * Creates a ChangeSet from attributes only (legacy method).
     *
     * @deprecated Use fromInput() instead to populate both attributes and relationships.
     *             This method will be removed in version 2.0.
     *
     * @param string $type Resource type
     * @param array<string, mixed> $attributes Attribute name => value map
     * @return ChangeSet
     * @throws BadRequestException If unknown attributes are provided
     */
    public function fromAttributes(string $type, array $attributes): ChangeSet
    {
        trigger_error(
            'ChangeSetFactory::fromAttributes() is deprecated. Use fromInput() instead to populate both attributes and relationships.',
            E_USER_DEPRECATED
        );

        return $this->fromInput($type, $attributes, []);
    }
}
