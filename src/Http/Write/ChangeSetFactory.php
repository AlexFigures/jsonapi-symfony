<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Http\Write;

use AlexFigures\JsonApi\Contract\Data\ChangeSet;
use AlexFigures\JsonApi\Http\Exception\BadRequestException;
use AlexFigures\JsonApi\Resource\Metadata\AttributeMetadata;
use AlexFigures\JsonApi\Resource\Registry\ResourceRegistryInterface;

/** @internal */
final readonly class ChangeSetFactory
{
    public function __construct(private ResourceRegistryInterface $registry)
    {
    }

    /**
     * Creates a ChangeSet from JSON:API input data (attributes and relationships).
     *
     * This is the recommended method for creating ChangeSets as it populates both
     * attributes and relationships, providing a unified data flow.
     *
     * @param  string                            $type          Resource type
     * @param  array<string, mixed>              $attributes    Attribute name => value map
     * @param  array<string, array{data: mixed}> $relationships Relationship name => JSON:API relationship data
     * @return ChangeSet
     * @throws BadRequestException               If unknown attributes are provided
     */
    public function fromInput(string $type, array $attributes, array $relationships = []): ChangeSet
    {
        $metadata = $this->registry->getByType($type);
        $mappedAttributes = [];

        foreach ($attributes as $name => $value) {
            if (!isset($metadata->attributes[$name])) {
                throw new BadRequestException(sprintf('Unknown attribute "%s" for type "%s".', $name, $type), [new \AlexFigures\JsonApi\Http\Error\ErrorObject(null, null, '400', 'unknown-attribute', 'Unknown Attribute', 'Unknown attribute.', new \AlexFigures\JsonApi\Http\Error\ErrorSource(pointer: '/data/attributes/' . $name))]);
            }

            /** @var AttributeMetadata $attribute */
            $attribute = $metadata->attributes[$name];
            $path = $attribute->propertyPath ?? $name;
            $mappedAttributes[$path] = $value;
        }

        return new ChangeSet($mappedAttributes, $relationships);
    }

}
