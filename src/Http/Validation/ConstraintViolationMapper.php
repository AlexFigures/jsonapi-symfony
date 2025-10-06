<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Validation;

use JsonApi\Symfony\Http\Error\ErrorMapper;
use JsonApi\Symfony\Http\Error\ErrorObject;
use JsonApi\Symfony\Resource\Metadata\AttributeMetadata;
use JsonApi\Symfony\Resource\Metadata\RelationshipMetadata;
use JsonApi\Symfony\Resource\Metadata\ResourceMetadata;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;
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

        foreach ($violations as $violation) {
            if (!$violation instanceof ConstraintViolationInterface) {
                continue;
            }

            [$pointer, $meta] = $this->pointerFor($metadata, (string) $violation->getPropertyPath());
            $errors[] = $this->errors->validationError($pointer, (string) $violation->getMessage(), $meta);
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
            if (!$attribute instanceof AttributeMetadata) {
                continue;
            }

            $attributeName = $attribute->name;
            $propertyPath = $attribute->propertyPath ?? $attributeName;
            $attributeMap[$attributeName] = sprintf('/data/attributes/%s', $attributeName);
            $attributeMap[$propertyPath] = sprintf('/data/attributes/%s', $attributeName);
        }

        $relationshipMap = [];
        foreach ($metadata->relationships as $relationship) {
            if (!$relationship instanceof RelationshipMetadata) {
                continue;
            }

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
