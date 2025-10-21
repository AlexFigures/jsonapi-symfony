<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Validation;

use AlexFigures\Symfony\Http\Error\ErrorMapper;
use AlexFigures\Symfony\Http\Error\ErrorObject;
use AlexFigures\Symfony\Http\Exception\ValidationException;
use AlexFigures\Symfony\Resource\Metadata\ResourceMetadata;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

final class ConstraintViolationMapper
{
    public function __construct(
        private readonly ResourceRegistryInterface $registry,
        private readonly ErrorMapper $errors,
    ) {
    }

    /**
     * @return list<ErrorObject>
     */
    public function map(string $resourceType, ConstraintViolationListInterface $violations): array
    {
        $metadata = $this->registry->getByType($resourceType);
        $errors = [];
        $seenPointers = [];

        foreach ($violations as $violation) {
            /** @var ConstraintViolationInterface $violation */
            [$pointer, $meta] = $this->pointerFor($metadata, (string) $violation->getPropertyPath());

            if (isset($seenPointers[$pointer])) {
                continue;
            }

            $seenPointers[$pointer] = true;
            $errors[] = $this->errors->validationError($pointer, (string) $violation->getMessage(), $meta);
        }

        return $errors;
    }

    /**
     * Converts violations to ValidationException.
     */
    public function mapToException(string $resourceType, ConstraintViolationListInterface $violations): ValidationException
    {
        $errors = $this->map($resourceType, $violations);
        return new ValidationException($errors);
    }

    /**
     * Maps denormalization errors to ValidationException.
     *
     * Handles various Serializer exceptions and converts them to JSON:API errors
     * with proper source pointers.
     */
    public function mapDenormErrors(string $resourceType, \Throwable $exception): ValidationException
    {
        $errors = [];

        if ($exception instanceof PartialDenormalizationException) {
            $errors = $this->mapPartialDenormalizationErrors($resourceType, $exception);
        } elseif ($exception instanceof NotNormalizableValueException) {
            $errors = $this->mapNotNormalizableValueError($resourceType, $exception);
        } elseif ($exception instanceof ExtraAttributesException) {
            $errors = $this->mapExtraAttributesError($resourceType, $exception);
        } else {
            // Fallback for unknown denormalization errors
            $errors[] = $this->errors->validationError(
                '/data',
                $exception->getMessage(),
                ['exception' => get_class($exception)]
            );
        }

        return new ValidationException($errors);
    }

    /**
     * Maps PartialDenormalizationException errors to JSON:API errors.
     *
     * @return list<ErrorObject>
     */
    private function mapPartialDenormalizationErrors(string $resourceType, PartialDenormalizationException $exception): array
    {
        $metadata = $this->registry->getByType($resourceType);
        $errors = [];

        /** @var array<int|string, \Throwable> $rawErrors */
        $rawErrors = $exception->getErrors();
        foreach ($rawErrors as $path => $nestedException) {
            if ($nestedException instanceof NotNormalizableValueException) {
                // Prefer the exception's own path over the array key
                // The exception's path is usually more accurate (e.g., "publishedAt" vs "0")
                $exceptionPath = $nestedException->getPath();
                $pathToUse = ($exceptionPath !== null && $exceptionPath !== '') ? $exceptionPath : (string) $path;
                $errors[] = $this->mapSingleNotNormalizableValueError($metadata, $nestedException, $pathToUse);
            } else {
                // Handle other nested exceptions
                [$pointer, $meta] = $this->pointerFor($metadata, (string) $path);
                $errors[] = $this->errors->validationError(
                    $pointer,
                    $nestedException->getMessage(),
                    array_merge($meta, ['exception' => get_class($nestedException)])
                );
            }
        }

        return $errors;
    }

    /**
     * Maps NotNormalizableValueException to JSON:API error.
     *
     * @return list<ErrorObject>
     */
    private function mapNotNormalizableValueError(string $resourceType, NotNormalizableValueException $exception): array
    {
        $metadata = $this->registry->getByType($resourceType);
        return [$this->mapSingleNotNormalizableValueError($metadata, $exception)];
    }

    /**
     * Maps single NotNormalizableValueException to JSON:API error.
     */
    private function mapSingleNotNormalizableValueError(
        ResourceMetadata $metadata,
        NotNormalizableValueException $exception,
        ?string $overridePath = null
    ): ErrorObject {
        $path = $overridePath ?? $exception->getPath() ?? '';

        // If path is empty or numeric, try to extract property name from exception message
        // Message format: 'Failed to denormalize attribute "propertyName" value for class...'
        // or 'Failed to create object because the class misses the "propertyName" property.'
        if ($path === '' || is_numeric($path)) {
            $message = $exception->getMessage();
            if (preg_match('/attribute "([^"]+)"/', $message, $matches)) {
                $path = $matches[1];
            } elseif (preg_match('/property path "([^"]+)"/', $message, $matches)) {
                $path = $matches[1];
            } elseif (preg_match('/misses the "([^"]+)" property/', $message, $matches)) {
                $path = $matches[1];
            }
        }

        [$pointer, $meta] = $this->pointerFor($metadata, $path);

        $message = $exception->getMessage();
        $expectedTypes = $exception->getExpectedTypes();

        // Detect missing required field error and customize message
        if (str_contains($message, 'misses the') && str_contains($message, 'property')) {
            $message = 'This value is required.';
        } elseif (!empty($expectedTypes)) {
            $message = sprintf(
                'Invalid value. Expected type: %s. %s',
                implode('|', $expectedTypes),
                $message
            );
        }

        return $this->errors->validationError(
            $pointer,
            $message,
            array_merge($meta, [
                'expectedTypes' => $expectedTypes,
                'actualValue' => $exception->getCurrentType(),
            ])
        );
    }

    /**
     * Maps ExtraAttributesException to JSON:API errors.
     *
     * @return list<ErrorObject>
     */
    private function mapExtraAttributesError(string $resourceType, ExtraAttributesException $exception): array
    {
        $metadata = $this->registry->getByType($resourceType);
        $errors = [];

        foreach ($exception->getExtraAttributes() as $attribute) {
            // Skip numeric attributes - these are Symfony Serializer bug artifacts
            // when COLLECT_DENORMALIZATION_ERRORS is used with ALLOW_EXTRA_ATTRIBUTES = false
            if (is_int($attribute)) {
                continue;
            }

            // Cast to string to handle both string and integer keys
            $attributeString = (string) $attribute;
            [$pointer, $meta] = $this->pointerFor($metadata, $attributeString);
            $errors[] = $this->errors->validationError(
                $pointer,
                sprintf('Unknown attribute "%s" is not allowed.', $attributeString),
                array_merge($meta, ['extraAttribute' => $attributeString])
            );
        }

        return $errors;
    }

    /**
     * @return array{string, array<string, mixed>}
     */
    private function pointerFor(ResourceMetadata $metadata, string $propertyPath): array
    {
        $normalized = $this->normalizePropertyPath(trim($propertyPath));
        if ($normalized === '') {
            return ['/data', []];
        }

        [$attributeMap, $relationshipMap] = $this->buildMaps($metadata);
        $firstSegment = $this->firstSegment($normalized);
        $remainder = substr($normalized, strlen($firstSegment));

        if (isset($attributeMap[$firstSegment])) {
            $pointer = $attributeMap[$firstSegment];
            // Handle nested property paths for embeddables (e.g., "contactInfo.email")
            if ($remainder !== '' && str_starts_with($remainder, '.')) {
                $nestedPath = substr($remainder, 1); // Remove leading dot
                $pointer .= '.' . $nestedPath;
            }
            return [$pointer, []];
        }

        if (isset($relationshipMap[$firstSegment])) {
            $pointer = $relationshipMap[$firstSegment];
            if (preg_match('/^\[(\d+)\]/', $remainder, $matches) === 1) {
                $pointer .= '/' . $matches[1];
            }

            return [$pointer, []];
        }

        if (isset($attributeMap[$normalized])) {
            return [$attributeMap[$normalized], []];
        }

        if (isset($relationshipMap[$normalized])) {
            return [$relationshipMap[$normalized], []];
        }

        $meta = ['propertyPath' => $propertyPath];
        if ($firstSegment !== '') {
            if (isset($attributeMap[$firstSegment])) {
                return [$attributeMap[$firstSegment], $meta];
            }

            if (isset($relationshipMap[$firstSegment])) {
                return [$relationshipMap[$firstSegment], $meta];
            }
        }

        // For unknown attributes, generate a precise pointer using the property path
        // This is especially important for extra attributes validation
        return [sprintf('/data/attributes/%s', $normalized), $meta];
    }

    /**
     * Normalizes property paths from denormalization exceptions.
     *
     * Converts paths like "[propertyName]", "data[propertyName]", "[0]", or "0"
     * to just "propertyName".
     *
     * Denormalization exceptions often use array indices (like "[0]") for the first
     * property in the data array. We need to map these back to actual property names.
     */
    private function normalizePropertyPath(string $path): string
    {
        // Strip array bracket notation: [propertyName] -> propertyName
        $normalized = preg_replace('/^\[([^\]]+)\]$/', '$1', $path) ?? $path;

        // Strip data prefix: data[propertyName] -> propertyName
        $normalized = preg_replace('/^data\[([^\]]+)\]/', '$1', $normalized) ?? $normalized;

        // If the path is just a numeric index (like "0"), we can't determine the property name
        // from the path alone. The caller should use the exception's context to determine
        // the actual property name. For now, return the numeric index as-is.

        return $normalized;
    }

    /**
     * @return array{array<string, string>, array<string, string>}
     */
    private function buildMaps(ResourceMetadata $metadata): array
    {
        $attributeMap = [];
        foreach ($metadata->attributes as $attribute) {
            $attributeName = $attribute->name;
            $propertyPath = $attribute->propertyPath ?? $attributeName;
            $attributeMap[$attributeName] = sprintf('/data/attributes/%s', $attributeName);
            $attributeMap[$propertyPath] = sprintf('/data/attributes/%s', $attributeName);
        }

        $relationshipMap = [];
        foreach ($metadata->relationships as $relationship) {
            $relationshipName = $relationship->name;
            $propertyPath = $relationship->propertyPath ?? $relationshipName;
            $pointer = sprintf('/data/relationships/%s/data', $relationshipName);
            $relationshipMap[$relationshipName] = $pointer;
            $relationshipMap[$propertyPath] = $pointer;
        }

        return [$attributeMap, $relationshipMap];
    }

    private function firstSegment(string $propertyPath): string
    {
        if (preg_match('/^([^\.\[]+)/', $propertyPath, $matches) === 1) {
            return $matches[1];
        }

        return $propertyPath;
    }
}
