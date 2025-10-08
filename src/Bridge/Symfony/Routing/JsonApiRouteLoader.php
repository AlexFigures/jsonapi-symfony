<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Bridge\Symfony\Routing;

use JsonApi\Symfony\Http\Controller\OpenApiController;
use JsonApi\Symfony\Http\Controller\SwaggerUiController;
use JsonApi\Symfony\Resource\Registry\CustomRouteRegistryInterface;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Automatic route loader for JSON:API resources.
 *
 * Scans all registered resources and generates standard CRUD routes:
 * - GET    /{prefix}/{type}           - resource collection
 * - POST   /{prefix}/{type}           - resource creation
 * - GET    /{prefix}/{type}/{id}      - resource retrieval
 * - PATCH  /{prefix}/{type}/{id}      - resource update
 * - DELETE /{prefix}/{type}/{id}      - resource deletion
 *
 * Also generates routes for relationships:
 * - GET    /{prefix}/{type}/{id}/relationships/{relationship}
 * - POST   /{prefix}/{type}/{id}/relationships/{relationship}
 * - PATCH  /{prefix}/{type}/{id}/relationships/{relationship}
 * - DELETE /{prefix}/{type}/{id}/relationships/{relationship}
 *
 * Usage:
 *
 * ```yaml
 * # config/routes.yaml
 * jsonapi_auto:
 *     resource: .
 *     type: jsonapi
 * ```
 */
final class JsonApiRouteLoader extends Loader
{
    private bool $loaded = false;

    /**
     * @param array{enabled?: bool, route?: string} $openApiConfig
     * @param array{enabled?: bool, route?: string} $docsUiConfig
     */
    public function __construct(
        private readonly ResourceRegistryInterface $registry,
        private readonly string $routePrefix = '/api',
        private readonly bool $enableRelationshipRoutes = true,
        private readonly array $openApiConfig = [],
        private readonly array $docsUiConfig = [],
        private readonly ?RouteNameGenerator $routeNameGenerator = null,
        private readonly ?CustomRouteRegistryInterface $customRouteRegistry = null,
    ) {
        parent::__construct();
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        if ($this->loaded) {
            throw new \RuntimeException('Do not add the "jsonapi" loader twice');
        }

        $this->loaded = true;
        $routes = new RouteCollection();

        // Add high-priority custom routes first (priority > 0)
        $this->addCustomRoutes($routes, true);

        foreach ($this->registry->all() as $metadata) {
            $resourceType = $metadata->type;
            $prefix = rtrim($this->routePrefix, '/');

            // Collection routes
            $routes->add(
                $this->generateRouteName($resourceType, 'index'),
                new Route(
                    path: "{$prefix}/{$resourceType}",
                    defaults: [
                        '_controller' => 'JsonApi\Symfony\Http\Controller\CollectionController',
                        'type' => $resourceType,
                    ],
                    methods: ['GET'],
                )
            );

            $routes->add(
                $this->generateRouteName($resourceType, 'create'),
                new Route(
                    path: "{$prefix}/{$resourceType}",
                    defaults: [
                        '_controller' => 'JsonApi\Symfony\Http\Controller\CreateResourceController',
                        'type' => $resourceType,
                    ],
                    methods: ['POST'],
                )
            );

            // Resource routes
            $routes->add(
                $this->generateRouteName($resourceType, 'show'),
                new Route(
                    path: "{$prefix}/{$resourceType}/{id}",
                    defaults: [
                        '_controller' => 'JsonApi\Symfony\Http\Controller\ResourceController',
                        'type' => $resourceType,
                    ],
                    requirements: ['id' => '.+'],
                    methods: ['GET'],
                )
            );

            $routes->add(
                $this->generateRouteName($resourceType, 'update'),
                new Route(
                    path: "{$prefix}/{$resourceType}/{id}",
                    defaults: [
                        '_controller' => 'JsonApi\Symfony\Http\Controller\UpdateResourceController',
                        'type' => $resourceType,
                    ],
                    requirements: ['id' => '.+'],
                    methods: ['PATCH'],
                )
            );

            $routes->add(
                $this->generateRouteName($resourceType, 'delete'),
                new Route(
                    path: "{$prefix}/{$resourceType}/{id}",
                    defaults: [
                        '_controller' => 'JsonApi\Symfony\Http\Controller\DeleteResourceController',
                        'type' => $resourceType,
                    ],
                    requirements: ['id' => '.+'],
                    methods: ['DELETE'],
                )
            );

            // Relationship routes
            if ($this->enableRelationshipRoutes && count($metadata->relationships) > 0) {
                foreach ($metadata->relationships as $relationship) {
                    $relationshipName = $relationship->name;

                    // GET relationship
                    $routes->add(
                        $this->generateRouteName($resourceType, null, $relationshipName, 'show'),
                        new Route(
                            path: "{$prefix}/{$resourceType}/{id}/relationships/{$relationshipName}",
                            defaults: [
                                '_controller' => 'JsonApi\Symfony\Http\Controller\RelationshipGetController',
                                'type' => $resourceType,
                                'relationship' => $relationshipName,
                            ],
                            requirements: ['id' => '.+'],
                            methods: ['GET'],
                        )
                    );

                    // PATCH relationship (replace)
                    $routes->add(
                        $this->generateRouteName($resourceType, null, $relationshipName, 'update'),
                        new Route(
                            path: "{$prefix}/{$resourceType}/{id}/relationships/{$relationshipName}",
                            defaults: [
                                '_controller' => 'JsonApi\Symfony\Http\Controller\RelationshipWriteController',
                                'type' => $resourceType,
                                'relationship' => $relationshipName,
                            ],
                            requirements: ['id' => '.+'],
                            methods: ['PATCH'],
                        )
                    );

                    // POST relationship (add to-many)
                    if ($relationship->toMany) {
                        $routes->add(
                            $this->generateRouteName($resourceType, null, $relationshipName, 'add'),
                            new Route(
                                path: "{$prefix}/{$resourceType}/{id}/relationships/{$relationshipName}",
                                defaults: [
                                    '_controller' => 'JsonApi\Symfony\Http\Controller\RelationshipWriteController',
                                    'type' => $resourceType,
                                    'relationship' => $relationshipName,
                                ],
                                requirements: ['id' => '.+'],
                                methods: ['POST'],
                            )
                        );

                        // DELETE relationship (remove from to-many)
                        $routes->add(
                            $this->generateRouteName($resourceType, null, $relationshipName, 'remove'),
                            new Route(
                                path: "{$prefix}/{$resourceType}/{id}/relationships/{$relationshipName}",
                                defaults: [
                                    '_controller' => 'JsonApi\Symfony\Http\Controller\RelationshipWriteController',
                                    'type' => $resourceType,
                                    'relationship' => $relationshipName,
                                ],
                                requirements: ['id' => '.+'],
                                methods: ['DELETE'],
                            )
                        );
                    }

                    // Related resource routes
                    $routes->add(
                        $this->generateRouteName($resourceType, null, $relationshipName),
                        new Route(
                            path: "{$prefix}/{$resourceType}/{id}/{$relationshipName}",
                            defaults: [
                                '_controller' => 'JsonApi\Symfony\Http\Controller\RelatedController',
                                'type' => $resourceType,
                                'relationship' => $relationshipName,
                            ],
                            requirements: ['id' => '.+'],
                            methods: ['GET'],
                        )
                    );
                }
            }
        }

        // Add low-priority custom routes after auto-generated routes (priority <= 0)
        $this->addCustomRoutes($routes, false);
        $this->addDocumentationRoutes($routes);

        return $routes;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $type === 'jsonapi';
    }

    private function addCustomRoutes(RouteCollection $routes, bool $highPriority = false): void
    {
        if ($this->customRouteRegistry === null) {
            return;
        }

        foreach ($this->customRouteRegistry->all() as $customRoute) {
            // Filter routes by priority
            if ($highPriority && $customRoute->priority <= 0) {
                continue; // Skip low-priority routes when adding high-priority ones
            }
            if (!$highPriority && $customRoute->priority > 0) {
                continue; // Skip high-priority routes when adding low-priority ones
            }
            $routeName = $customRoute->name;

            // Apply route name transformation if configured
            // Only transform names that match the exact canonical pattern: jsonapi.{type}.{action}
            // Leave custom names like 'jsonapi.products.actions.publish' untouched
            if ($this->routeNameGenerator !== null && str_starts_with($routeName, 'jsonapi.')) {
                $parts = explode('.', $routeName);
                if (count($parts) === 3 && $parts[0] === 'jsonapi') {
                    // Only transform if it's exactly the canonical 3-part pattern
                    $resourceType = $parts[1];
                    $action = $parts[2];
                    $routeName = $this->generateRouteName($resourceType, $action);
                }
                // For any other pattern (e.g., 'jsonapi.products.actions.publish'), leave the name unchanged
            }

            $defaults = array_merge($customRoute->defaults, [
                '_controller' => $customRoute->controller,
            ]);

            if ($customRoute->resourceType !== null) {
                $defaults['type'] = $customRoute->resourceType;
            }

            $routes->add(
                $routeName,
                new Route(
                    path: $customRoute->path,
                    defaults: $defaults,
                    requirements: $customRoute->requirements,
                    methods: $customRoute->methods,
                )
            );
        }
    }

    private function addDocumentationRoutes(RouteCollection $routes): void
    {
        if (($this->openApiConfig['enabled'] ?? false) === true) {
            $path = $this->openApiConfig['route'] ?? '/_jsonapi/openapi.json';

            $routes->add(
                'jsonapi.docs.openapi',
                new Route(
                    path: $path,
                    defaults: [
                        '_controller' => OpenApiController::class,
                    ],
                    methods: ['GET'],
                )
            );
        }

        if (($this->docsUiConfig['enabled'] ?? false) === true) {
            $path = $this->docsUiConfig['route'] ?? '/_jsonapi/docs';

            $routes->add(
                'jsonapi.docs.ui',
                new Route(
                    path: $path,
                    defaults: [
                        '_controller' => SwaggerUiController::class,
                    ],
                    methods: ['GET'],
                )
            );
        }
    }

    /**
     * Generate a route name using the configured naming convention.
     */
    private function generateRouteName(
        string $resourceType,
        ?string $action,
        ?string $relationship = null,
        ?string $relationshipAction = null
    ): string {
        if ($this->routeNameGenerator !== null) {
            return $this->routeNameGenerator->generateRouteName($resourceType, $action, $relationship, $relationshipAction);
        }

        // Fallback to legacy naming for backward compatibility
        if ($relationship !== null) {
            if ($relationshipAction !== null) {
                return "jsonapi.{$resourceType}.relationships.{$relationship}.{$relationshipAction}";
            }
            return "jsonapi.{$resourceType}.related.{$relationship}";
        }

        if ($action === null) {
            throw new \InvalidArgumentException('Action cannot be null for non-relationship routes');
        }

        return "jsonapi.{$resourceType}.{$action}";
    }
}
