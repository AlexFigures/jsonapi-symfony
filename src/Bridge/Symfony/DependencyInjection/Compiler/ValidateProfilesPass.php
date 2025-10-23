<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Bridge\Symfony\DependencyInjection\Compiler;

use AlexFigures\Symfony\Profile\AttributeReader;
use AlexFigures\Symfony\Profile\ProfileInterface;
use AlexFigures\Symfony\Profile\Validation\FieldRequirement;
use AlexFigures\Symfony\Profile\Validation\ProfileRequirements;
use AlexFigures\Symfony\Profile\Validation\ValidationError;
use AlexFigures\Symfony\Profile\Validation\ValidationResult;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Validates profile requirements at compile-time.
 *
 * This compiler pass ensures that all enabled profiles have their requirements
 * satisfied by the entities they are applied to. This prevents runtime errors
 * by catching configuration issues during container compilation.
 *
 * Validation errors block container compilation, while warnings are logged.
 *
 * @internal
 */
final class ValidateProfilesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // Skip validation if profiles are not configured
        if (!$container->hasParameter('jsonapi.profiles.enabled_by_default')
            && !$container->hasParameter('jsonapi.profiles.per_type')) {
            return;
        }

        // Collect all profiles
        $profilesByUri = $this->collectProfiles($container);

        // Collect all resource types
        $resourceTypes = $this->collectResourceTypes($container);

        // Collect enabled profiles per resource type
        $enabledProfiles = $this->collectEnabledProfiles($container, $resourceTypes);

        // Validate using reflection (no Doctrine dependency)
        $result = $this->validateWithReflection($profilesByUri, $resourceTypes, $enabledProfiles);

        // Handle validation result
        if ($result->hasErrors()) {
            $this->handleErrors($result);
        }

        if ($result->hasWarnings()) {
            $this->handleWarnings($result);
        }
    }

    /**
     * Collect all registered profiles.
     *
     * @return array<string, ProfileInterface>
     */
    private function collectProfiles(ContainerBuilder $container): array
    {
        $profiles = [];
        $taggedServices = $container->findTaggedServiceIds('jsonapi.profile');

        foreach ($taggedServices as $serviceId => $tags) {
            $definition = $container->getDefinition($serviceId);
            $class = $definition->getClass();

            if ($class === null) {
                continue;
            }

            // Instantiate profile to get its URI
            // Note: This assumes profiles have no required constructor dependencies
            // or have default values for all parameters
            try {
                $profile = new $class();
                if ($profile instanceof ProfileInterface) {
                    $profiles[$profile->uri()] = $profile;
                }
            } catch (\Throwable) {
                // Skip profiles that cannot be instantiated
                continue;
            }
        }

        return $profiles;
    }

    /**
     * Collect all resource types and their entity classes.
     *
     * @return array<string, class-string>
     */
    private function collectResourceTypes(ContainerBuilder $container): array
    {
        $resourceTypes = [];

        // Get discovered resources from ResourceDiscoveryPass
        if ($container->hasParameter('jsonapi.discovered_resources')) {
            /** @var array<string, class-string> $discoveredResources */
            $discoveredResources = $container->getParameter('jsonapi.discovered_resources');

            foreach ($discoveredResources as $type => $class) {
                $resourceTypes[$type] = $class;
            }
        }

        return $resourceTypes;
    }

    /**
     * Collect enabled profiles per resource type.
     *
     * @param  array<string, class-string> $resourceTypes
     * @return array<string, list<string>>
     */
    private function collectEnabledProfiles(ContainerBuilder $container, array $resourceTypes): array
    {
        $enabledProfiles = [];

        // Get globally enabled profiles
        $globalProfiles = [];
        if ($container->hasParameter('jsonapi.profiles.enabled_by_default')) {
            /** @var list<string> $globalProfiles */
            $globalProfiles = $container->getParameter('jsonapi.profiles.enabled_by_default');
        }

        // Get per-type enabled profiles
        $perTypeProfiles = [];
        if ($container->hasParameter('jsonapi.profiles.per_type')) {
            /** @var array<string, list<string>> $perTypeProfiles */
            $perTypeProfiles = $container->getParameter('jsonapi.profiles.per_type');
        }

        // Merge global and per-type profiles
        foreach ($resourceTypes as $resourceType => $entityClass) {
            $profiles = $globalProfiles;

            if (isset($perTypeProfiles[$resourceType])) {
                $profiles = array_values(array_unique(array_merge($profiles, $perTypeProfiles[$resourceType])));
            }

            if (!empty($profiles)) {
                $enabledProfiles[$resourceType] = $profiles;
            }
        }

        return $enabledProfiles;
    }

    /**
     * Validate profiles using reflection (no Doctrine dependency).
     *
     * @param array<string, ProfileInterface> $profilesByUri
     * @param array<string, class-string>     $resourceTypes
     * @param array<string, list<string>>     $enabledProfiles
     */
    private function validateWithReflection(
        array $profilesByUri,
        array $resourceTypes,
        array $enabledProfiles
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

    /**
     * Handle validation errors by throwing an exception.
     *
     * @param  \AlexFigures\Symfony\Profile\Validation\ValidationResult $result
     * @throws \RuntimeException
     */
    private function handleErrors(object $result): void
    {
        $errors = $result->formatErrors();
        $message = sprintf(
            "Profile validation failed with %d error(s):\n\n%s\n\nFix these errors before deploying to production.",
            $result->getErrorCount(),
            implode("\n", $errors)
        );

        throw new \RuntimeException($message);
    }

    /**
     * Handle validation warnings by logging them.
     *
     * @param \AlexFigures\Symfony\Profile\Validation\ValidationResult $result
     */
    private function handleWarnings(object $result): void
    {
        $warnings = $result->formatWarnings();

        // In Symfony compiler passes, we can't easily access the logger
        // So we'll just trigger a deprecation notice for each warning
        foreach ($warnings as $warning) {
            @trigger_error($warning, \E_USER_DEPRECATED);
        }
    }
}
