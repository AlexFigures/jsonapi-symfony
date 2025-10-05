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
     * @param array<string, mixed> $attributes
     */
    public function fromAttributes(string $type, array $attributes): ChangeSet
    {
        $metadata = $this->registry->getByType($type);
        $mapped = [];

        foreach ($attributes as $name => $value) {
            if (!isset($metadata->attributes[$name])) {
                throw new BadRequestException(sprintf('Unknown attribute "%s" for type "%s".', $name, $type));
            }

            /** @var AttributeMetadata $attribute */
            $attribute = $metadata->attributes[$name];
            $path = $attribute->propertyPath ?? $name;
            $mapped[$path] = $value;
        }

        return new ChangeSet($mapped);
    }
}
