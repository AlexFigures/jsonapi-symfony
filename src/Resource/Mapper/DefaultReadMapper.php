<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Resource\Mapper;

use AlexFigures\JsonApi\Query\Criteria;
use AlexFigures\JsonApi\Resource\Definition\ResourceDefinition;
use ReflectionClass;
use RuntimeException;

/** @internal */
final class DefaultReadMapper implements ReadMapperInterface
{
    public function toView(mixed $row, ResourceDefinition $definition, Criteria $criteria): object
    {
        if (is_object($row)) {
            return $row;
        }

        if (!is_array($row)) {
            throw new RuntimeException(sprintf('Cannot map row of type %s to view object.', get_debug_type($row)));
        }

        /** @var class-string $viewClass */
        $viewClass = $definition->getEffectiveViewClass();
        $reflection = new ReflectionClass($viewClass);

        if (!$reflection->isInstantiable()) {
            throw new RuntimeException(sprintf('View class "%s" is not instantiable.', $viewClass));
        }

        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            $instance = $reflection->newInstance();
            foreach ($row as $field => $value) {
                if (!is_string($field)) {
                    continue;
                }

                if ($reflection->hasProperty($field)) {
                    $property = $reflection->getProperty($field);
                    if ($property->isPublic()) {
                        $property->setValue($instance, $value);
                    }
                }
            }

            return $instance;
        }

        $arguments = [];
        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();
            if (array_key_exists($name, $row)) {
                $arguments[] = $row[$name];
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }

            $arguments[] = null;
        }

        return $reflection->newInstanceArgs($arguments);
    }
}
