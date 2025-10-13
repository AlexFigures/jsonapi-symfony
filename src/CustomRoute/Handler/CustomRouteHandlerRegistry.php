<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\CustomRoute\Handler;

use AlexFigures\Symfony\Resource\Metadata\CustomRouteMetadata;
use AlexFigures\Symfony\Resource\Registry\CustomRouteRegistryInterface;
use Psr\Container\ContainerInterface;
use RuntimeException;

/**
 * Registry for custom route handlers.
 *
 * This registry maps route names to handler service IDs and provides
 * handler instances via the service container.
 *
 * @internal
 */
final class CustomRouteHandlerRegistry
{
    /**
     * @var array<string, string> Map of route name => handler service ID
     */
    private array $handlers = [];

    public function __construct(
        private readonly CustomRouteRegistryInterface $customRouteRegistry,
        private readonly ContainerInterface $handlerLocator,
    ) {
        $this->buildHandlerMap();
    }

    /**
     * Get a handler instance by route name.
     *
     * @param string $routeName The route name (e.g., 'articles.publish')
     *
     * @return CustomRouteHandlerInterface The handler instance
     *
     * @throws RuntimeException if handler is not found or not properly configured
     */
    public function get(string $routeName): CustomRouteHandlerInterface
    {
        if (!isset($this->handlers[$routeName])) {
            throw new RuntimeException(sprintf(
                'No handler registered for route "%s". Make sure the route has a "handler" parameter.',
                $routeName
            ));
        }

        $handlerServiceId = $this->handlers[$routeName];

        if (!$this->handlerLocator->has($handlerServiceId)) {
            throw new RuntimeException(sprintf(
                'Handler service "%s" for route "%s" not found in container.',
                $handlerServiceId,
                $routeName
            ));
        }

        $handler = $this->handlerLocator->get($handlerServiceId);

        if (!$handler instanceof CustomRouteHandlerInterface) {
            throw new RuntimeException(sprintf(
                'Handler "%s" for route "%s" must implement %s.',
                $handlerServiceId,
                $routeName,
                CustomRouteHandlerInterface::class
            ));
        }

        return $handler;
    }

    /**
     * Check if a handler is registered for a route.
     *
     * @param string $routeName The route name
     *
     * @return bool True if a handler is registered
     */
    public function has(string $routeName): bool
    {
        return isset($this->handlers[$routeName]);
    }

    /**
     * Get all registered route names.
     *
     * @return list<string> Array of route names
     */
    public function getRouteNames(): array
    {
        return array_keys($this->handlers);
    }

    /**
     * Build the handler map from custom route metadata.
     */
    private function buildHandlerMap(): void
    {
        foreach ($this->customRouteRegistry->all() as $route) {
            // Only register routes that have a handler
            if ($route->isHandlerBased()) {
                $this->handlers[$route->name] = $route->handler;
            }
        }
    }
}
