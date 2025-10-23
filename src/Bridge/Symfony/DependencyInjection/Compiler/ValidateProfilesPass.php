<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Bridge\Symfony\DependencyInjection\Compiler;

use AlexFigures\Symfony\Profile\AttributeReader;
use AlexFigures\Symfony\Profile\ProfileInterface;
use AlexFigures\Symfony\Profile\Validation\ProfileValidator;
use AlexFigures\Symfony\Resource\Attribute\JsonApiResource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

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

        // Create validator and validate
        $validator = $this->createValidator($container);
        $result = $validator->validate($profilesByUri, $resourceTypes, $enabledProfiles);

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
            /** @var list<array{type: string, class: class-string}> $discoveredResources */
            $discoveredResources = $container->getParameter('jsonapi.discovered_resources');

            foreach ($discoveredResources as $resource) {
                $resourceTypes[$resource['type']] = $resource['class'];
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
     * Create ProfileValidator instance.
     */
    private function createValidator(ContainerBuilder $container): ProfileValidator
    {
        // Get EntityManager from container
        $entityManagerRef = new Reference('doctrine.orm.entity_manager');
        /** @var \Doctrine\ORM\EntityManagerInterface $entityManager */
        $entityManager = $container->get((string) $entityManagerRef);

        // Create AttributeReader
        $attributeReader = new AttributeReader();

        return new ProfileValidator($entityManager, $attributeReader);
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
