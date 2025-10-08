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
     * @param string|null $handler Handler class name (new in 0.3.0, takes precedence over controller)
     * @param string|null $controller Controller class name (legacy, for backward compatibility)
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
        public readonly ?string $handler,
        public readonly ?string $controller,
        public readonly ?string $resourceType,
        public readonly array $defaults,
        public readonly array $requirements,
        public readonly ?string $description,
        public readonly int $priority,
    ) {
    }

    /**
     * Check if this route uses a handler (new approach).
     */
    public function isHandlerBased(): bool
    {
        return $this->handler !== null;
    }

    /**
     * Get the controller or handler class name.
     * For handler-based routes, returns the handler class.
     * For controller-based routes, returns the controller class.
     */
    public function getControllerOrHandler(): string
    {
        return $this->handler ?? $this->controller ?? throw new \LogicException(
            'CustomRouteMetadata must have either handler or controller set'
        );
    }
}
