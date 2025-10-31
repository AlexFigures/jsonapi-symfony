<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Bridge\Symfony\Routing;

use AlexFigures\Symfony\CustomRoute\Controller\CustomRouteController;
use AlexFigures\Symfony\Http\Controller\OpenApiController;
use AlexFigures\Symfony\Http\Controller\SwaggerUiController;
use AlexFigures\Symfony\Resource\Definition\ResourceOperation;
use AlexFigures\Symfony\Resource\Metadata\CustomRouteMetadata;
use AlexFigures\Symfony\Resource\Registry\CustomRouteRegistryInterface;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
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
            $allowedOperations = $metadata->allowedOperations;

            // Collection routes - only if INDEX operation is allowed
            if ($this->isOperationAllowed(ResourceOperation::INDEX, $allowedOperations)) {
                $routes->add(
                    $this->generateRouteName($resourceType, 'index'),
                    new Route(
                        path: "{$prefix}/{$resourceType}",
                        defaults: [
                            '_controller' => 'AlexFigures\Symfony\Http\Controller\CollectionController',
                            'type' => $resourceType,
                        ],
                        methods: ['GET'],
                    )
                );
            }

            // Create route - only if CREATE operation is allowed
            if ($this->isOperationAllowed(ResourceOperation::CREATE, $allowedOperations)) {
                $routes->add(
                    $this->generateRouteName($resourceType, 'create'),
                    new Route(
                        path: "{$prefix}/{$resourceType}",
                        defaults: [
                            '_controller' => 'AlexFigures\Symfony\Http\Controller\CreateResourceController',
                            'type' => $resourceType,
                        ],
                        methods: ['POST'],
                    )
                );
            }

            // Show route - only if SHOW operation is allowed
            if ($this->isOperationAllowed(ResourceOperation::SHOW, $allowedOperations)) {
                $routes->add(
                    $this->generateRouteName($resourceType, 'show'),
                    new Route(
                        path: "{$prefix}/{$resourceType}/{id}",
                        defaults: [
                            '_controller' => 'AlexFigures\Symfony\Http\Controller\ResourceController',
                            'type' => $resourceType,
                        ],
                        requirements: ['id' => '[^/]+'],
                        methods: ['GET'],
                    )
                );
            }

            // Update route - only if UPDATE operation is allowed
            if ($this->isOperationAllowed(ResourceOperation::UPDATE, $allowedOperations)) {
                $routes->add(
                    $this->generateRouteName($resourceType, 'update'),
                    new Route(
                        path: "{$prefix}/{$resourceType}/{id}",
                        defaults: [
                            '_controller' => 'AlexFigures\Symfony\Http\Controller\UpdateResourceController',
                            'type' => $resourceType,
                        ],
                        requirements: ['id' => '[^/]+'],
                        methods: ['PATCH'],
                    )
                );
            }

            // Delete route - only if DELETE operation is allowed
            if ($this->isOperationAllowed(ResourceOperation::DELETE, $allowedOperations)) {
                $routes->add(
                    $this->generateRouteName($resourceType, 'delete'),
                    new Route(
                        path: "{$prefix}/{$resourceType}/{id}",
                        defaults: [
                            '_controller' => 'AlexFigures\Symfony\Http\Controller\DeleteResourceController',
                            'type' => $resourceType,
                        ],
                        requirements: ['id' => '[^/]+'],
                        methods: ['DELETE'],
                    )
                );
            }

            // OPTIONS route for collection endpoint
            // Always register if at least one collection operation is allowed
            if ($this->isOperationAllowed(ResourceOperation::INDEX, $allowedOperations)
                || $this->isOperationAllowed(ResourceOperation::CREATE, $allowedOperations)) {
                $routes->add(
                    $this->generateRouteName($resourceType, 'options'),
                    new Route(
                        path: "{$prefix}/{$resourceType}",
                        defaults: [
                            '_controller' => 'AlexFigures\Symfony\Http\Controller\OptionsController::collection',
                            'type' => $resourceType,
                        ],
                        methods: ['OPTIONS'],
                    )
                );
            }

            // OPTIONS route for resource endpoint
            // Always register if at least one resource operation is allowed
            if ($this->isOperationAllowed(ResourceOperation::SHOW, $allowedOperations)
                || $this->isOperationAllowed(ResourceOperation::UPDATE, $allowedOperations)
                || $this->isOperationAllowed(ResourceOperation::DELETE, $allowedOperations)) {
                $routes->add(
                    $this->generateRouteName($resourceType, 'options.resource'),
                    new Route(
                        path: "{$prefix}/{$resourceType}/{id}",
                        defaults: [
                            '_controller' => 'AlexFigures\Symfony\Http\Controller\OptionsController::resource',
                            'type' => $resourceType,
                        ],
                        requirements: ['id' => '[^/]+'],
                        methods: ['OPTIONS'],
                    )
                );
            }

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
                                '_controller' => 'AlexFigures\Symfony\Http\Controller\RelationshipGetController',
                                'type' => $resourceType,
                                'rel' => $relationshipName,
                            ],
                            requirements: ['id' => '[^/]+'],
                            methods: ['GET'],
                        )
                    );

                    // PATCH relationship (replace)
                    $routes->add(
                        $this->generateRouteName($resourceType, null, $relationshipName, 'update'),
                        new Route(
                            path: "{$prefix}/{$resourceType}/{id}/relationships/{$relationshipName}",
                            defaults: [
                                '_controller' => 'AlexFigures\Symfony\Http\Controller\RelationshipWriteController',
                                'type' => $resourceType,
                                'rel' => $relationshipName,
                            ],
                            requirements: ['id' => '[^/]+'],
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
                                    '_controller' => 'AlexFigures\Symfony\Http\Controller\RelationshipWriteController',
                                    'type' => $resourceType,
                                    'rel' => $relationshipName,
                                ],
                                requirements: ['id' => '[^/]+'],
                                methods: ['POST'],
                            )
                        );

                        // DELETE relationship (remove from to-many)
                        $routes->add(
                            $this->generateRouteName($resourceType, null, $relationshipName, 'remove'),
                            new Route(
                                path: "{$prefix}/{$resourceType}/{id}/relationships/{$relationshipName}",
                                defaults: [
                                    '_controller' => 'AlexFigures\Symfony\Http\Controller\RelationshipWriteController',
                                    'type' => $resourceType,
                                    'rel' => $relationshipName,
                                ],
                                requirements: ['id' => '[^/]+'],
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
                                '_controller' => 'AlexFigures\Symfony\Http\Controller\RelatedController',
                                'type' => $resourceType,
                                'rel' => $relationshipName,
                            ],
                            requirements: ['id' => '[^/]+'],
                            methods: ['GET'],
                        )
                    );

                    // OPTIONS route for relationship endpoint
                    $routes->add(
                        $this->generateRouteName($resourceType, null, $relationshipName, 'options'),
                        new Route(
                            path: "{$prefix}/{$resourceType}/{id}/relationships/{$relationshipName}",
                            defaults: [
                                '_controller' => 'AlexFigures\Symfony\Http\Controller\OptionsController::relationship',
                                'type' => $resourceType,
                                'rel' => $relationshipName,
                            ],
                            requirements: ['id' => '[^/]+'],
                            methods: ['OPTIONS'],
                        )
                    );

                    // OPTIONS route for related resource endpoint
                    $routes->add(
                        $this->generateRouteName($resourceType, null, $relationshipName, 'options.related'),
                        new Route(
                            path: "{$prefix}/{$resourceType}/{id}/{$relationshipName}",
                            defaults: [
                                '_controller' => 'AlexFigures\Symfony\Http\Controller\OptionsController::related',
                                'type' => $resourceType,
                                'rel' => $relationshipName,
                            ],
                            requirements: ['id' => '[^/]+'],
                            methods: ['OPTIONS'],
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

            // Determine controller based on whether this is a handler-based or controller-based route
            $controller = $this->resolveController($customRoute);

            $defaults = array_merge($customRoute->defaults, [
                '_controller' => $controller,
            ]);

            // For handler-based routes, pass the route name as a parameter
            if ($customRoute->isHandlerBased()) {
                $defaults['routeName'] = $customRoute->name;
            }

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
     * Generate a route name using snake_case convention.
     */
    private function generateRouteName(
        string $resourceType,
        ?string $action,
        ?string $relationship = null,
        ?string $relationshipAction = null
    ): string {
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

    /**
     * Resolve the controller for a custom route.
     *
     * For handler-based routes (new in 0.3.0), routes to CustomRouteController.
     * For controller-based routes (legacy), uses the specified controller.
     */
    private function resolveController(CustomRouteMetadata $customRoute): string
    {
        if ($customRoute->isHandlerBased()) {
            // Handler-based routes go through CustomRouteController
            return CustomRouteController::class;
        }

        // Controller-based routes use the specified controller
        return $customRoute->controller ?? throw new \RuntimeException(
            sprintf('Custom route "%s" has no controller or handler configured.', $customRoute->name)
        );
    }

    /**
     * Check if an operation is allowed for a resource.
     *
     * @param list<ResourceOperation> $allowedOperations
     */
    private function isOperationAllowed(ResourceOperation $operation, array $allowedOperations): bool
    {
        foreach ($allowedOperations as $allowed) {
            if ($allowed === $operation) {
                return true;
            }
        }

        return false;
    }
}
