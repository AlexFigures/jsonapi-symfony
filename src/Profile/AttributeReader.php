<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Profile;

/**
 * Reads PHP 8 attributes from entity classes.
 *
 * Simplified version that only checks the class itself (no inheritance).
 */
final class AttributeReader
{
    /**
     * Check if a class has a specific attribute.
     *
     * @param class-string $className FQCN of the class to check
     * @param class-string $attribute FQCN of the attribute to look for
     */
    public function hasAttribute(string $className, string $attribute): bool
    {
        try {
            $reflection = new \ReflectionClass($className);
            $attributes = $reflection->getAttributes($attribute);

            return !empty($attributes);
        } catch (\ReflectionException) {
            return false;
        }
    }

    /**
     * Get an attribute instance from a class.
     *
     * @template T of object
     * @param  class-string    $className FQCN of the class to check
     * @param  class-string<T> $attribute FQCN of the attribute to get
     * @return T|null          The attribute instance, or null if not found
     */
    public function getAttribute(string $className, string $attribute): ?object
    {
        try {
            $reflection = new \ReflectionClass($className);
            $attributes = $reflection->getAttributes($attribute);

            if (empty($attributes)) {
                return null;
            }

            return $attributes[0]->newInstance();
        } catch (\ReflectionException) {
            return null;
        }
    }

    /**
     * Get all attributes of a specific type from a class.
     *
     * @template T of object
     * @param  class-string    $className FQCN of the class to check
     * @param  class-string<T> $attribute FQCN of the attribute to get
     * @return list<T>         List of attribute instances
     */
    public function getAttributes(string $className, string $attribute): array
    {
        try {
            $reflection = new \ReflectionClass($className);
            $attributes = $reflection->getAttributes($attribute);

            return array_map(
                static fn (\ReflectionAttribute $attr) => $attr->newInstance(),
                $attributes
            );
        } catch (\ReflectionException) {
            return [];
        }
    }
}
