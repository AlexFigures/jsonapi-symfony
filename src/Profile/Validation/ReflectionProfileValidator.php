<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Profile\Validation;

use AlexFigures\JsonApi\Profile\AttributeReader;
use AlexFigures\JsonApi\Profile\ProfileInterface;

/** @internal Validates actual profile services without invoking constructors through reflection. */
final class ReflectionProfileValidator
{
    /**
     * Validate profiles using reflection (no Doctrine dependency).
     *
     * @param array<string, ProfileInterface> $profilesByUri
     * @param array<string, class-string>     $resourceTypes
     * @param array<string, list<string>>     $enabledProfiles
     */
    public function validate(
        array $profilesByUri,
        array $resourceTypes,
        array $enabledProfiles,
        bool $deferUnknownProfiles = false,
    ): ValidationResult {
        $result = new ValidationResult();
        $attributeReader = new AttributeReader();

        foreach ($enabledProfiles as $resourceType => $profileUris) {
            if (!isset($resourceTypes[$resourceType])) {
                // Resource type not found - this is a configuration error
                foreach ($profileUris as $profileUri) {
                    $result->addIssue(ValidationError::error(
                        $profileUri,
                        $resourceType,
                        "Resource type '{$resourceType}' not found in resource registry"
                    ));
                }
                continue;
            }

            $entityClass = $resourceTypes[$resourceType];

            foreach ($profileUris as $profileUri) {
                if (!isset($profilesByUri[$profileUri])) {
                    if ($deferUnknownProfiles) {
                        continue;
                    }
                    $result->addIssue(ValidationError::error(
                        $profileUri,
                        $resourceType,
                        "Profile '{$profileUri}' not found in profile registry"
                    ));
                    continue;
                }

                $profile = $profilesByUri[$profileUri];
                $this->validateProfileForEntity($profile, $resourceType, $entityClass, $result, $attributeReader);
            }
        }

        return $result;
    }

    /**
     * Validate a single profile for a single entity using reflection.
     *
     * @param class-string $entityClass
     */
    private function validateProfileForEntity(
        ProfileInterface $profile,
        string $resourceType,
        string $entityClass,
        ValidationResult $result,
        AttributeReader $attributeReader
    ): void {
        $requirements = $profile->requirements();

        // If profile has no requirements, nothing to validate
        if ($requirements === null) {
            return;
        }

        // Validate required attribute
        if ($requirements->requiresAttribute()) {
            $requiredAttribute = $requirements->getRequiredAttribute();
            if ($requiredAttribute !== null) {
                /** @var class-string $requiredAttribute */
                if (!$attributeReader->hasAttribute($entityClass, $requiredAttribute)) {
                    $result->addIssue(ValidationError::error(
                        $profile->uri(),
                        $resourceType,
                        sprintf(
                            "Entity '%s' must have #[%s] attribute to use this profile",
                            $entityClass,
                            $this->getShortClassName($requiredAttribute)
                        )
                    ));
                }
            }
        }

        // Validate required fields using reflection
        if ($requirements->hasFieldRequirements()) {
            $this->validateFieldsWithReflection($profile, $resourceType, $entityClass, $requirements, $result);
        }
    }

    /**
     * Validate fields using reflection instead of Doctrine metadata.
     *
     * @param class-string $entityClass
     */
    private function validateFieldsWithReflection(
        ProfileInterface $profile,
        string $resourceType,
        string $entityClass,
        ProfileRequirements $requirements,
        ValidationResult $result
    ): void {
        if (!class_exists($entityClass)) {
            $result->addIssue(ValidationError::error(
                $profile->uri(),
                $resourceType,
                sprintf("Cannot load class '%s': class does not exist", $entityClass)
            ));
            return;
        }

        /** @var \ReflectionClass<object> $reflection */
        $reflection = new \ReflectionClass($entityClass);

        foreach ($requirements->getFieldRequirements() as $fieldName => $requirement) {
            $this->validateFieldWithReflection($profile, $resourceType, $reflection, $fieldName, $requirement, $result);
        }
    }

    /**
     * Validate a single field using reflection.
     *
     * @param \ReflectionClass<object> $reflection
     */
    private function validateFieldWithReflection(
        ProfileInterface $profile,
        string $resourceType,
        \ReflectionClass $reflection,
        string $fieldName,
        FieldRequirement $requirement,
        ValidationResult $result
    ): void {
        // Check if property exists
        if (!$reflection->hasProperty($fieldName)) {
            if ($requirement->isRequired()) {
                $result->addIssue(ValidationError::error(
                    $profile->uri(),
                    $resourceType,
                    sprintf(
                        "Missing required field '%s': %s",
                        $fieldName,
                        $requirement->description ?: 'no description'
                    ),
                    $fieldName
                ));
            }
            return;
        }

        $property = $reflection->getProperty($fieldName);

        // Validate type if specified
        $propertyType = $property->getType();
        if ($propertyType instanceof \ReflectionNamedType) {
            $actualType = $propertyType->getName();

            // Simple type matching (can be enhanced)
            if (!$this->typesMatch($requirement->type, $actualType)) {
                $severity = $requirement->isRequired() ? 'error' : 'warning';
                $result->addIssue(ValidationError::$severity(
                    $profile->uri(),
                    $resourceType,
                    sprintf(
                        "Field '%s' type mismatch: expected '%s', got '%s'",
                        $fieldName,
                        $requirement->type,
                        $actualType
                    ),
                    $fieldName
                ));
            }

            // Validate nullable constraint
            if (!$requirement->nullable && $propertyType->allowsNull()) {
                $result->addIssue(ValidationError::warning(
                    $profile->uri(),
                    $resourceType,
                    sprintf(
                        "Field '%s' is nullable but profile expects non-nullable",
                        $fieldName
                    ),
                    $fieldName
                ));
            }
        }
    }

    /**
     * Check if types match (simplified version).
     */
    private function typesMatch(string $expectedType, string $actualType): bool
    {
        // Normalize types
        $expectedType = $this->normalizeType($expectedType);
        $actualType = $this->normalizeType($actualType);

        return $expectedType === $actualType;
    }

    /**
     * Normalize type for comparison.
     */
    private function normalizeType(string $type): string
    {
        // Map common type aliases
        $typeMap = [
            'integer' => 'int',
            'boolean' => 'bool',
            'double' => 'float',
        ];

        $normalized = strtolower(trim($type));
        return $typeMap[$normalized] ?? $normalized;
    }

    /**
     * Get short class name without namespace.
     */
    private function getShortClassName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);
        return end($parts);
    }

}
