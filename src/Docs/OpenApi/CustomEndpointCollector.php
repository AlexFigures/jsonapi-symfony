<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Docs\OpenApi;

use AlexFigures\Symfony\Docs\Attribute\OpenApiEndpoint;
use ReflectionClass;
use Symfony\Component\Routing\RouterInterface;

/**
 * Collects custom endpoints annotated with OpenApiEndpoint attribute.
 *
 * @internal
 */
final class CustomEndpointCollector
{
    /**
     * @var list<CustomEndpointMetadata>|null
     */
    private ?array $endpoints = null;

    public function __construct(
        private readonly RouterInterface $router,
    ) {
    }

    /**
     * Collect all custom endpoints with OpenAPI metadata.
     *
     * @return list<CustomEndpointMetadata>
     */
    public function collect(): array
    {
        if ($this->endpoints !== null) {
            return $this->endpoints;
        }

        $this->endpoints = [];

        foreach ($this->router->getRouteCollection() as $route) {
            $controller = $route->getDefault('_controller');
            if ($controller === null || !is_string($controller)) {
                continue;
            }

            // Parse controller string (e.g., "App\Controller\UploadController::upload")
            $parts = explode('::', $controller);
            if (count($parts) !== 2) {
                continue;
            }

            [$className, $methodName] = $parts;

            if (!class_exists($className)) {
                continue;
            }

            try {
                $reflection = new ReflectionClass($className);
                if (!$reflection->hasMethod($methodName)) {
                    continue;
                }

                $method = $reflection->getMethod($methodName);
                $attributes = $method->getAttributes(OpenApiEndpoint::class);

                if ($attributes === []) {
                    continue;
                }

                // Get the first OpenApiEndpoint attribute
                $openApiAttribute = $attributes[0]->newInstance();

                // Extract path and methods from route
                $path = $route->getPath();
                $methods = $route->getMethods();

                // If no methods specified, default to GET
                if ($methods === []) {
                    $methods = ['GET'];
                }

                // Create metadata for each HTTP method
                foreach ($methods as $httpMethod) {
                    $this->endpoints[] = new CustomEndpointMetadata(
                        path: $path,
                        method: strtolower($httpMethod),
                        openApi: $openApiAttribute,
                    );
                }
            } catch (\ReflectionException) {
                // Skip if reflection fails
                continue;
            }
        }

        return $this->endpoints;
    }
}
