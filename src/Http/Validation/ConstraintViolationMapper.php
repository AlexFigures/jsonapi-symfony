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

        /** @var array<int|string, list<\Throwable>> $rawErrors */
        $rawErrors = $exception->getErrors();
        foreach ($rawErrors as $path => $nestedExceptions) {
            $pathString = (string) $path;
            foreach ($nestedExceptions as $nestedException) {
                if ($nestedException instanceof NotNormalizableValueException) {
                    $errors[] = $this->mapSingleNotNormalizableValueError($metadata, $nestedException, $pathString);
                } else {
                    // Handle other nested exceptions
                    [$pointer, $meta] = $this->pointerFor($metadata, $pathString);
                    $errors[] = $this->errors->validationError(
                        $pointer,
                        $nestedException->getMessage(),
                        array_merge($meta, ['exception' => get_class($nestedException)])
                    );
                }
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
        [$pointer, $meta] = $this->pointerFor($metadata, $path);

        $message = $exception->getMessage();
        $expectedTypes = $exception->getExpectedTypes();

        if (!empty($expectedTypes)) {
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
            [$pointer, $meta] = $this->pointerFor($metadata, $attribute);
            $errors[] = $this->errors->validationError(
                $pointer,
                sprintf('Unknown attribute "%s" is not allowed.', $attribute),
                array_merge($meta, ['extraAttribute' => $attribute])
            );
        }

        return $errors;
    }

    /**
     * @return array{string, array<string, mixed>}
     */
    private function pointerFor(ResourceMetadata $metadata, string $propertyPath): array
    {
        $normalized = trim($propertyPath);
        if ($normalized === '') {
            return ['/data', []];
        }

        [$attributeMap, $relationshipMap] = $this->buildMaps($metadata);
        $firstSegment = $this->firstSegment($normalized);
        $remainder = substr($normalized, strlen($firstSegment));

        if (isset($attributeMap[$firstSegment])) {
            return [$attributeMap[$firstSegment], []];
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

        return ['/data/attributes', $meta];
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
