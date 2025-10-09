<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Bridge\Symfony\DependencyInjection\Compiler;

use JsonApi\Symfony\CustomRoute\Handler\CustomRouteHandlerInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Automatically registers custom route handlers as tagged services.
 *
 * This compiler pass scans for all services implementing CustomRouteHandlerInterface
 * and tags them with 'jsonapi.custom_route_handler' so they can be located by
 * the CustomRouteHandlerRegistry.
 *
 * This allows handlers to be automatically discovered without manual registration,
 * similar to how controllers are auto-registered in Symfony.
 *
 * @internal
 */
final class CustomRouteHandlerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // Find all services implementing CustomRouteHandlerInterface
        foreach ($container->getDefinitions() as $definition) {
            $class = $definition->getClass();

            // Skip if class is not set or is abstract
            if ($class === null || $definition->isAbstract()) {
                continue;
            }

            // Resolve parameter placeholders in class name
            $class = $container->getParameterBag()->resolveValue($class);

            // Skip if class is not a string (can be an expression)
            if (!is_string($class)) {
                continue;
            }

            // Skip if class doesn't exist or can't be loaded
            // Wrap in try-catch to handle classes with missing dependencies
            try {
                if (!class_exists($class)) {
                    continue;
                }
            } catch (\Throwable) {
                // Class exists but can't be loaded (missing dependencies, syntax errors, etc.)
                // This is fine - just skip it
                continue;
            }

            // Check if class implements CustomRouteHandlerInterface
            // Also wrap this in try-catch in case reflection fails
            try {
                if (!is_subclass_of($class, CustomRouteHandlerInterface::class)) {
                    continue;
                }
            } catch (\Throwable) {
                // Reflection failed - skip this class
                continue;
            }

            // Tag the service for handler locator
            $definition->addTag('jsonapi.custom_route_handler');

            // Ensure the service is public so it can be located
            // (Service locators need public services)
            $definition->setPublic(true);
        }
    }
}
