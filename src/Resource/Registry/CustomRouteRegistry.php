<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Resource\Registry;

use JsonApi\Symfony\Resource\Metadata\CustomRouteMetadata;

/**
 * Registry for custom JSON:API routes.
 *
 * @internal
 */
final class CustomRouteRegistry implements CustomRouteRegistryInterface
{
    /**
     * @var array<CustomRouteMetadata>
     */
    private array $routes = [];

    /**
     * @param iterable<CustomRouteMetadata|array<string, mixed>> $routes
     */
    public function __construct(iterable $routes = [])
    {
        foreach ($routes as $route) {
            if (is_array($route)) {
                // Reconstruct CustomRouteMetadata from serialized array
                $route = new CustomRouteMetadata(
                    name: $route['name'],
                    path: $route['path'],
                    methods: $route['methods'],
                    handler: $route['handler'] ?? null,
                    controller: $route['controller'] ?? null,
                    resourceType: $route['resourceType'] ?? null,
                    defaults: $route['defaults'] ?? [],
                    requirements: $route['requirements'] ?? [],
                    description: $route['description'] ?? null,
                    priority: $route['priority'] ?? 0,
                );
            }
            $this->addRoute($route);
        }
    }

    public function addRoute(CustomRouteMetadata $route): void
    {
        $this->routes[] = $route;
    }

    public function all(): array
    {
        // Sort by priority (higher priority first)
        $routes = $this->routes;
        usort($routes, static fn (CustomRouteMetadata $a, CustomRouteMetadata $b) => $b->priority <=> $a->priority);

        return $routes;
    }

    public function getByResourceType(string $resourceType): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (CustomRouteMetadata $route) => $route->resourceType === $resourceType
        ));
    }
}
