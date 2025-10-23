<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Profile\Validation;

use AlexFigures\Symfony\Profile\AttributeReader;
use AlexFigures\Symfony\Profile\ProfileInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * Validates that entities meet profile requirements.
 *
 * Checks:
 * - Required attributes are present on entity classes
 * - Required fields exist in entity metadata
 * - Field types match requirements
 * - Nullable constraints are satisfied
 */
final class ProfileValidator
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AttributeReader $attributeReader,
    ) {
    }

    /**
     * Validate all enabled profiles for all resource types.
     *
     * @param array<string, ProfileInterface> $profilesByUri   Map of profile URI => profile instance
     * @param array<string, class-string>     $resourceTypes   Map of resource type => entity class
     * @param array<string, list<string>>     $enabledProfiles Map of resource type => list of enabled profile URIs
     */
    public function validate(
        array $profilesByUri,
        array $resourceTypes,
        array $enabledProfiles
    ): ValidationResult {
        $result = new ValidationResult();

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
                    $result->addIssue(ValidationError::error(
                        $profileUri,
                        $resourceType,
                        "Profile '{$profileUri}' not found in profile registry"
                    ));
                    continue;
                }

                $profile = $profilesByUri[$profileUri];
                $this->validateProfileForEntity($profile, $resourceType, $entityClass, $result);
            }
        }

        return $result;
    }

    /**
     * Validate a single profile for a single entity.
     */
    private function validateProfileForEntity(
        ProfileInterface $profile,
        string $resourceType,
        string $entityClass,
        ValidationResult $result
    ): void {
        $requirements = $profile->requirements();

        // If profile has no requirements, nothing to validate
        if ($requirements === null) {
            return;
        }

        // Validate required attribute
        if ($requirements->requiresAttribute()) {
            $this->validateAttribute($profile, $resourceType, $entityClass, $requirements, $result);
        }

        // Validate required fields
        if ($requirements->hasFieldRequirements()) {
            $this->validateFields($profile, $resourceType, $entityClass, $requirements, $result);
        }
    }

    /**
     * Validate that entity has required attribute.
     */
    private function validateAttribute(
        ProfileInterface $profile,
        string $resourceType,
        string $entityClass,
        ProfileRequirements $requirements,
        ValidationResult $result
    ): void {
        $requiredAttribute = $requirements->getRequiredAttribute();

        if ($requiredAttribute === null) {
            return;
        }

        /** @var class-string $entityClass */
        $entityClass = $entityClass;
        /** @var class-string $requiredAttribute */
        $requiredAttribute = $requiredAttribute;

        if (!$this->attributeReader->hasAttribute($entityClass, $requiredAttribute)) {
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

    /**
     * Validate that entity has all required fields with correct types.
     */
    private function validateFields(
        ProfileInterface $profile,
        string $resourceType,
        string $entityClass,
        ProfileRequirements $requirements,
        ValidationResult $result
    ): void {
        try {
            $metadata = $this->entityManager->getClassMetadata($entityClass);
        } catch (\Exception $e) {
            $result->addIssue(ValidationError::error(
                $profile->uri(),
                $resourceType,
                sprintf("Cannot load metadata for entity '%s': %s", $entityClass, $e->getMessage())
            ));
            return;
        }

        foreach ($requirements->getFieldRequirements() as $fieldName => $requirement) {
            $this->validateField($profile, $resourceType, $metadata, $fieldName, $requirement, $result);
        }
    }

    /**
     * Validate a single field requirement.
     *
     * @param ClassMetadata<object> $metadata
     */
    private function validateField(
        ProfileInterface $profile,
        string $resourceType,
        ClassMetadata $metadata,
        string $fieldName,
        FieldRequirement $requirement,
        ValidationResult $result
    ): void {
        // Check if field exists
        if (!$metadata->hasField($fieldName) && !$metadata->hasAssociation($fieldName)) {
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
            } else {
                $result->addIssue(ValidationError::warning(
                    $profile->uri(),
                    $resourceType,
                    sprintf(
                        "Optional field '%s' not found: %s",
                        $fieldName,
                        $requirement->description ?: 'no description'
                    ),
                    $fieldName
                ));
            }
            return;
        }

        // Validate field type (only for regular fields, not associations)
        if ($metadata->hasField($fieldName)) {
            $fieldMapping = $metadata->getFieldMapping($fieldName);
            /** @var string $actualType */
            $actualType = $fieldMapping['type'];

            if (!$requirement->matchesType($actualType)) {
                $severity = $requirement->isRequired() ? 'error' : 'warning';
                $result->addIssue(ValidationError::$severity(
                    $profile->uri(),
                    $resourceType,
                    sprintf(
                        "Field '%s' type mismatch: expected '%s', got '%s'",
                        $fieldName,
                        $requirement->type,
                        (string) $actualType
                    ),
                    $fieldName
                ));
            }

            // Validate nullable constraint
            $isNullable = $fieldMapping['nullable'] ?? false;
            if (!$requirement->nullable && $isNullable) {
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
     * Get short class name without namespace.
     */
    private function getShortClassName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);
        return end($parts);
    }
}
