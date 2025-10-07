<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Bridge\Symfony\DependencyInjection\Compiler;

use JsonApi\Symfony\Resource\Attribute\JsonApiResource;
use LogicException;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Finder\Finder;

/**
 * Automatically discovers JSON:API resources by scanning configured directories.
 *
 * This compiler pass scans directories for classes with the #[JsonApiResource] attribute
 * and registers them in the ResourceRegistry. This allows Entity classes and other
 * non-service classes to be automatically discovered without manual registration.
 *
 * Similar to how API Platform and Doctrine ORM discover resources/entities.
 *
 * @internal
 */
final class ResourceDiscoveryPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('jsonapi.resource_paths')) {
            return;
        }

        /** @var list<string> $resourcePaths */
        $resourcePaths = $container->getParameter('jsonapi.resource_paths');

        $discoveredResources = $this->discoverResources($resourcePaths, $container);

        // Store discovered resources as a parameter
        $container->setParameter('jsonapi.discovered_resources', $discoveredResources);

        // Update ResourceRegistry definition to use discovered resources
        if ($container->hasDefinition('JsonApi\Symfony\Resource\Registry\ResourceRegistry')) {
            $registryDefinition = $container->getDefinition('JsonApi\Symfony\Resource\Registry\ResourceRegistry');

            // Replace the argument with discovered resources
            // The ResourceRegistry constructor accepts iterable<object|string>
            // We pass an array of class names (strings)
            $registryDefinition->setArgument(0, $discoveredResources);
        }
    }

    /**
     * @param list<string> $paths
     * @return array<string, class-string> Map of resource type => class name
     */
    private function discoverResources(array $paths, ContainerBuilder $container): array
    {
        $resources = [];

        foreach ($paths as $path) {
            // Resolve parameter placeholders (e.g., %kernel.project_dir%)
            $resolvedPath = $this->resolvePath($path, $container);

            if (!is_dir($resolvedPath)) {
                continue;
            }

            $finder = new Finder();
            $finder->files()->in($resolvedPath)->name('*.php');

            foreach ($finder as $file) {
                $className = $this->extractClassName($file->getPathname());

                if ($className === null || !class_exists($className)) {
                    continue;
                }

                try {
                    $reflection = new ReflectionClass($className);
                } catch (\ReflectionException) {
                    continue;
                }

                $attributes = $reflection->getAttributes(JsonApiResource::class);

                if (empty($attributes)) {
                    continue;
                }

                /** @var JsonApiResource $attribute */
                $attribute = $attributes[0]->newInstance();

                if (isset($resources[$attribute->type])) {
                    throw new LogicException(sprintf(
                        'Duplicate resource type "%s" found in classes %s and %s.',
                        $attribute->type,
                        $resources[$attribute->type],
                        $className
                    ));
                }

                $resources[$attribute->type] = $className;
            }
        }

        return $resources;
    }

    /**
     * Extract fully qualified class name from a PHP file.
     *
     * @param string $filePath Absolute path to the PHP file
     * @return class-string|null
     */
    private function extractClassName(string $filePath): ?string
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        // Extract namespace
        if (!preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatches)) {
            return null;
        }
        $namespace = trim($namespaceMatches[1]);

        // Extract class name
        if (!preg_match('/(?:class|interface|trait|enum)\s+(\w+)/', $content, $classMatches)) {
            return null;
        }
        $className = $classMatches[1];

        return $namespace . '\\' . $className;
    }

    /**
     * Resolve path with parameter placeholders using the container's parameter bag.
     */
    private function resolvePath(string $path, ContainerBuilder $container): string
    {
        // Use the container's parameter bag to resolve parameters
        return $container->getParameterBag()->resolveValue($path);
    }
}

