<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Bridge\Symfony\Serializer;

use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Type-coercing denormalizer that performs safe type conversions.
 *
 * This denormalizer wraps the data and performs safe type coercion before
 * passing it to the next denormalizer in the chain:
 *
 * Safe conversions (allowed):
 * - int → float (no precision loss)
 * - int → string (safe conversion)
 * - float → string (safe conversion)
 * - bool → string (safe conversion)
 *
 * Unsafe conversions (rejected):
 * - float → int (precision loss)
 * - string → int (may fail or lose data)
 * - string → float (may fail or lose data)
 * - string → bool (ambiguous)
 *
 * This denormalizer must be placed BEFORE ObjectNormalizer in the chain
 * to intercept and coerce data before strict type checking occurs.
 */
final class TypeCoercingDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    private const ALREADY_CALLED = 'TYPE_COERCING_DENORMALIZER_ALREADY_CALLED';

    /**
     * @param array<string, mixed> $context
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        // Mark that we've already processed this data to avoid infinite recursion
        $context[self::ALREADY_CALLED] = true;

        // Coerce the data if it's an array (object data)
        if (is_array($data) && $this->isStringKeyedArray($data)) {
            $data = $this->coerceArrayData($data, $type);
        }

        // Delegate to the next denormalizer in the chain
        return $this->denormalizer->denormalize($data, $type, $format, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        // Avoid infinite recursion
        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        // We support all types - we're a wrapper
        return true;
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['*' => false]; // Supports all types, but not as a priority
    }

    /**
     * Coerces array data by applying safe type conversions to scalar values.
     *
     * @param  array<string, mixed> $data
     * @param  string               $type
     * @return array<string, mixed>
     */
    private function coerceArrayData(array $data, string $type): array
    {
        if (!class_exists($type)) {
            return $data;
        }

        $reflection = new \ReflectionClass($type);

        $coercedData = [];

        foreach ($data as $key => $value) {
            // Skip non-scalar values (arrays, objects, null)
            if (!is_scalar($value)) {
                $coercedData[$key] = $value;
                continue;
            }

            // Try to get the property type
            $propertyType = $this->getPropertyType($reflection, $key);

            if ($propertyType === null) {
                $coercedData[$key] = $value;
                continue;
            }

            // Perform safe type coercion
            $coercedData[$key] = $this->coerceValue($value, $propertyType);
        }

        return $coercedData;
    }

    /**
     * Gets the expected type for a property.
     *
     * @param  \ReflectionClass<object> $reflection
     * @return string|null              The expected type (int, float, string, bool) or null if unknown
     */
    private function getPropertyType(\ReflectionClass $reflection, string $propertyName): ?string
    {
        if (!$reflection->hasProperty($propertyName)) {
            return null;
        }

        $property = $reflection->getProperty($propertyName);
        $type = $property->getType();

        if (!$type instanceof \ReflectionNamedType) {
            return null;
        }

        $typeName = $type->getName();

        // Skip enum types - they should be handled by BackedEnumNormalizer
        if (enum_exists($typeName)) {
            return null;
        }

        // Only handle scalar types
        if (!in_array($typeName, ['int', 'float', 'string', 'bool'], true)) {
            return null;
        }

        return $typeName;
    }

    /**
     * Performs safe type coercion.
     *
     * @param  scalar $value        The value to coerce
     * @param  string $expectedType The expected type (int, float, string, bool)
     * @return scalar The coerced value
     */
    private function coerceValue(int|float|string|bool $value, string $expectedType): int|float|string|bool
    {
        $actualType = get_debug_type($value);

        // No coercion needed if types match
        if ($actualType === $expectedType) {
            return $value;
        }

        // Safe conversions
        return match (true) {
            // int → float (safe: no precision loss)
            $actualType === 'int' && $expectedType === 'float' && is_int($value) => (float) $value,

            // int → string (safe)
            $actualType === 'int' && $expectedType === 'string' && is_int($value) => (string) $value,

            // float → string (safe)
            $actualType === 'float' && $expectedType === 'string' && is_float($value) => (string) $value,

            // bool → string (safe)
            $actualType === 'bool' && $expectedType === 'string' && is_bool($value) => $value ? 'true' : 'false',

            // No safe conversion available - return original value
            // The ObjectNormalizer will handle the type error
            default => $value,
        };
    }

    /**
     * Checks if an array has string keys (associative array).
     *
     * @param  array<mixed> $array
     * @return bool
     * @phpstan-assert-if-true array<string, mixed> $array
     */
    private function isStringKeyedArray(array $array): bool
    {
        if ($array === []) {
            return true;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }
}
