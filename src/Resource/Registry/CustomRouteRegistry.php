<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Resource\Registry;

use AlexFigures\Symfony\Resource\Metadata\CustomRouteMetadata;
use LogicException;

/**
 * Registry for custom JSON:API routes.
 *
 * @internal
 */
final class CustomRouteRegistry implements CustomRouteRegistryInterface
{
    /**
     * @var list<CustomRouteMetadata>
     */
    private array $routes = [];

    /**
     * @param iterable<CustomRouteMetadata|array<string, mixed>|mixed> $routes
     */
    public function __construct(iterable $routes = [])
    {
        foreach ($routes as $route) {
            if (is_array($route)) {
                if (array_is_list($route)) {
                    throw new LogicException('Serialized custom routes must be associative arrays.');
                }

                /** @var array<string, mixed> $route */
                $this->addRoute($this->hydrateRouteFromArray($route));
                continue;
            }

            if ($route instanceof CustomRouteMetadata) {
                $this->addRoute($route);
                continue;
            }

            throw new LogicException('Custom routes must be provided as CustomRouteMetadata instances or serialized arrays.');
        }
    }

    public function addRoute(CustomRouteMetadata $route): void
    {
        $this->routes[] = $route;
    }

    /**
     * @return list<CustomRouteMetadata>
     */
    public function all(): array
    {
        // Sort by priority (higher priority first)
        $routes = $this->routes;
        usort($routes, static fn (CustomRouteMetadata $a, CustomRouteMetadata $b) => $b->priority <=> $a->priority);

        return $routes;
    }

    /**
     * @return list<CustomRouteMetadata>
     */
    public function getByResourceType(string $resourceType): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (CustomRouteMetadata $route) => $route->resourceType === $resourceType
        ));
    }

    /**
     * @param array<string, mixed> $route
     */
    private function hydrateRouteFromArray(array $route): CustomRouteMetadata
    {
        $name = $this->requireString($route, 'name');
        $path = $this->requireString($route, 'path');
        $methods = $this->normalizeStringList($route['methods'] ?? [], 'methods');
        $handler = $this->normalizeOptionalString($route['handler'] ?? null, 'handler');
        $controller = $this->normalizeOptionalString($route['controller'] ?? null, 'controller');
        $resourceType = $this->normalizeOptionalString($route['resourceType'] ?? null, 'resourceType');
        $defaults = $this->normalizeAssociativeArray($route['defaults'] ?? [], 'defaults');
        $requirements = $this->normalizeStringMap($route['requirements'] ?? [], 'requirements');
        $description = $this->normalizeOptionalString($route['description'] ?? null, 'description');
        $priority = $this->normalizeInt($route['priority'] ?? 0, 'priority');

        return new CustomRouteMetadata(
            name: $name,
            path: $path,
            methods: $methods,
            handler: $handler,
            controller: $controller,
            resourceType: $resourceType,
            defaults: $defaults,
            requirements: $requirements,
            description: $description,
            priority: $priority,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function requireString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        if (!is_string($value) || $value === '') {
            throw new LogicException(sprintf('Custom route %s must be a non-empty string.', $key));
        }

        return $value;
    }

    private function normalizeOptionalString(mixed $value, string $context): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value)) {
            throw new LogicException(sprintf('Custom route %s must be a string or null.', $context));
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private function normalizeStringList(mixed $value, string $context): array
    {
        if ($value === null) {
            return [];
        }

        if (!is_array($value)) {
            throw new LogicException(sprintf('Custom route %s must be an array of strings.', $context));
        }

        $normalized = [];
        foreach ($value as $item) {
            if (!is_string($item) || $item === '') {
                throw new LogicException(sprintf('Custom route %s must contain only non-empty strings.', $context));
            }

            $normalized[] = $item;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeAssociativeArray(mixed $value, string $context): array
    {
        if ($value === null) {
            return [];
        }

        if (!is_array($value)) {
            throw new LogicException(sprintf('Custom route %s must be an array.', $context));
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            if (!is_string($key) || $key === '') {
                throw new LogicException(sprintf('Custom route %s must have string keys.', $context));
            }

            $normalized[$key] = $item;
        }

        return $normalized;
    }

    /**
     * @return array<string, string>
     */
    private function normalizeStringMap(mixed $value, string $context): array
    {
        if ($value === null) {
            return [];
        }

        if (!is_array($value)) {
            throw new LogicException(sprintf('Custom route %s must be an array of strings.', $context));
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            if (!is_string($key) || $key === '') {
                throw new LogicException(sprintf('Custom route %s must have string keys.', $context));
            }

            if (!is_string($item)) {
                throw new LogicException(sprintf('Custom route %s must contain only string values.', $context));
            }

            $normalized[$key] = $item;
        }

        return $normalized;
    }

    private function normalizeInt(mixed $value, string $context): int
    {
        if ($value === null) {
            return 0;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        throw new LogicException(sprintf('Custom route %s must be an integer.', $context));
    }
}
