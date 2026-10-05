<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Resource\Mapper;

use AlexFigures\Symfony\Resource\Definition\ResourceDefinition;
use AlexFigures\Symfony\Resource\Write\WriteContext;

final class DefaultWriteMapper implements WriteMapperInterface
{
    public function __construct(private readonly ?\Symfony\Component\PropertyAccess\PropertyAccessorInterface $accessor = null)
    {
    }

    public function instantiate(ResourceDefinition $definition, object $requestDto, WriteContext $context): object
    {
        $class = $definition->dataClass;

        return new $class();
    }

    public function apply(object $entity, object $requestDto, ResourceDefinition $definition, WriteContext $context): void
    {
        foreach (get_object_vars($requestDto) as $property => $value) {
            $property = (string) $property;
            if ($this->accessor !== null && $this->accessor->isWritable($entity, $property)) {
                $this->accessor->setValue($entity, $property, $value);
            } elseif (property_exists($entity, $property)) {
                $entity->{$property} = $value;
            }
        }
    }
}
