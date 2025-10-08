<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Resource\Metadata;

/**
 * Metadata for a custom JSON:API route.
 *
 * @internal
 */
final class CustomRouteMetadata
{
    /**
     * @param string $name Route name
     * @param string $path Route path pattern
     * @param array<string> $methods HTTP methods
     * @param string $controller Controller class name
     * @param string|null $resourceType Associated resource type (if any)
     * @param array<string, mixed> $defaults Route defaults
     * @param array<string, string> $requirements Route requirements
     * @param string|null $description Route description
     * @param int $priority Route priority
     */
    public function __construct(
        public readonly string $name,
        public readonly string $path,
        public readonly array $methods,
        public readonly string $controller,
        public readonly ?string $resourceType,
        public readonly array $defaults,
        public readonly array $requirements,
        public readonly ?string $description,
        public readonly int $priority,
    ) {
    }
}
