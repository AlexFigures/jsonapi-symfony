<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Bridge\Symfony\DependencyInjection\Compiler;

use AlexFigures\Symfony\Resource\Attribute\JsonApiCustomRoute;
use AlexFigures\Symfony\Resource\Attribute\JsonApiResource;
use LogicException;
use ReflectionClass;
use ReflectionMethod;
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
        $discoveredCustomRoutes = $this->discoverCustomRoutes($resourcePaths, $container);

        // Store discovered resources as a parameter
        $container->setParameter('jsonapi.discovered_resources', $discoveredResources);
        $container->setParameter('jsonapi.discovered_custom_routes', $discoveredCustomRoutes);

        // Update ResourceRegistry definition to use discovered resources
        if ($container->hasDefinition('AlexFigures\Symfony\Resource\Registry\ResourceRegistry')) {
            $registryDefinition = $container->getDefinition('AlexFigures\Symfony\Resource\Registry\ResourceRegistry');

            // Replace the argument with discovered resources
            // The ResourceRegistry constructor accepts iterable<object|string>
            // We pass an array of class names (strings)
            $registryDefinition->setArgument(0, $discoveredResources);
        }

        // Update CustomRouteRegistry definition to use discovered custom routes
        if ($container->hasDefinition('AlexFigures\Symfony\Resource\Registry\CustomRouteRegistry')) {
            $registryDefinition = $container->getDefinition('AlexFigures\Symfony\Resource\Registry\CustomRouteRegistry');
            $registryDefinition->setArgument(0, $discoveredCustomRoutes);
        }
    }

    /**
     * @param  list<string>                $paths
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

                $reflection = new ReflectionClass($className);

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
     * @param  string            $filePath Absolute path to the PHP file
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

        /** @var class-string $fqcn */
        $fqcn = $namespace . '\\' . $className;

        return $fqcn;
    }

    /**
     * @param  list<string>                $paths
     * @return array<array<string, mixed>> Serializable array of custom route data
     */
    private function discoverCustomRoutes(array $paths, ContainerBuilder $container): array
    {
        $customRoutes = [];

        foreach ($paths as $path) {
            $resolvedPath = $this->resolvePath($path, $container);

            if (!is_dir($resolvedPath)) {
                continue;
            }

            $finder = new Finder();
            $finder->files()->name('*.php')->in($resolvedPath);

            foreach ($finder as $file) {
                $className = $this->extractClassName($file->getPathname());
                if ($className === null || !class_exists($className)) {
                    continue;
                }

                try {
                    $reflection = new ReflectionClass($className);
                    $customRouteAttributes = $reflection->getAttributes(JsonApiCustomRoute::class);

                    foreach ($customRouteAttributes as $attribute) {
                        /** @var JsonApiCustomRoute $customRoute */
                        $customRoute = $attribute->newInstance();

                        // Determine handler, controller, and resource type
                        $handler = $customRoute->handler;
                        $controller = $customRoute->controller;
                        $resourceType = $customRoute->resourceType;

                        // If neither handler nor controller is provided, we need to determine it
                        if ($handler === null && $controller === null) {
                            // Check if this is a controller/handler class
                            $isControllerClass = $this->isControllerClass($reflection);

                            if ($isControllerClass) {
                                // For controller/handler classes, check if it's invokable
                                if ($reflection->hasMethod('__invoke')) {
                                    $controller = $className; // Invokable controller
                                } else {
                                    throw new \InvalidArgumentException(sprintf(
                                        'Custom route "%s" on controller class "%s" must specify a handler or controller parameter ' .
                                        'because the class is not invokable. Use handler: HandlerClass::class or controller: "%s::methodName" ' .
                                        'or make the class invokable by adding an __invoke method.',
                                        $customRoute->name,
                                        $className,
                                        $className
                                    ));
                                }
                            } else {
                                // For entity classes, handler or controller must be explicitly provided
                                throw new \InvalidArgumentException(sprintf(
                                    'Custom route "%s" on entity class "%s" must specify a handler or controller parameter.',
                                    $customRoute->name,
                                    $className
                                ));
                            }
                        }

                        // If used on an entity class, try to get resource type from JsonApiResource attribute
                        if ($resourceType === null) {
                            $resourceAttributes = $reflection->getAttributes(JsonApiResource::class);
                            if (count($resourceAttributes) > 0) {
                                /** @var JsonApiResource $resource */
                                $resource = $resourceAttributes[0]->newInstance();
                                $resourceType = $resource->type;
                            }
                        }

                        // Store as serializable array instead of CustomRouteMetadata object
                        // This allows the container to be dumped to XML/cache
                        $customRoutes[] = [
                            'name' => $customRoute->name,
                            'path' => $customRoute->path,
                            'methods' => $customRoute->methods,
                            'handler' => $handler,
                            'controller' => $controller,
                            'resourceType' => $resourceType,
                            'defaults' => $customRoute->defaults,
                            'requirements' => $customRoute->requirements,
                            'description' => $customRoute->description,
                            'priority' => $customRoute->priority,
                        ];
                    }
                } catch (\ReflectionException) {
                    // Skip classes that can't be reflected
                    continue;
                }
            }
        }

        return $customRoutes;
    }

    /**
     * Determines if a class is likely a controller class based on its characteristics.
     *
     * @param ReflectionClass<object> $reflection
     */
    private function isControllerClass(ReflectionClass $reflection): bool
    {
        $className = $reflection->getName();

        // Check if class name contains "Controller"
        if (str_contains($className, 'Controller')) {
            return true;
        }

        // Check if class has JsonApiResource attribute (likely an entity)
        if (count($reflection->getAttributes(JsonApiResource::class)) > 0) {
            return false;
        }

        // Check if class has public methods that could be controller actions
        /** @var list<ReflectionMethod> $publicMethods */
        $publicMethods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        foreach ($publicMethods as $method) {
            // Skip magic methods and inherited methods
            if ($method->isStatic() ||
                $method->isAbstract() ||
                str_starts_with($method->getName(), '__') ||
                $method->getDeclaringClass()->getName() !== $className) {
                continue;
            }

            // If it has public instance methods, it's likely a controller
            return true;
        }

        return false;
    }

    /**
     * Resolve path with parameter placeholders using the container's parameter bag.
     */
    private function resolvePath(string $path, ContainerBuilder $container): string
    {
        // Use the container's parameter bag to resolve parameters
        $resolved = $container->getParameterBag()->resolveValue($path);

        if (!is_string($resolved)) {
            throw new LogicException(sprintf('Resource path "%s" must resolve to a string value.', $path));
        }

        return $resolved;
    }
}
