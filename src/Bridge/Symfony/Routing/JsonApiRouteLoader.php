<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Bridge\Symfony\Routing;

use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Автоматический загрузчик роутов для JSON:API ресурсов.
 * 
 * Сканирует все зарегистрированные ресурсы и генерирует стандартные CRUD роуты:
 * - GET    /{prefix}/{type}           - список ресурсов
 * - POST   /{prefix}/{type}           - создание ресурса
 * - GET    /{prefix}/{type}/{id}      - получение ресурса
 * - PATCH  /{prefix}/{type}/{id}      - обновление ресурса
 * - DELETE /{prefix}/{type}/{id}      - удаление ресурса
 * 
 * Также генерирует роуты для relationships:
 * - GET    /{prefix}/{type}/{id}/relationships/{relationship}
 * - POST   /{prefix}/{type}/{id}/relationships/{relationship}
 * - PATCH  /{prefix}/{type}/{id}/relationships/{relationship}
 * - DELETE /{prefix}/{type}/{id}/relationships/{relationship}
 * 
 * Использование:
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

    public function __construct(
        private readonly ResourceRegistryInterface $registry,
        private readonly string $routePrefix = '/api',
        private readonly bool $enableRelationshipRoutes = true,
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

        foreach ($this->registry->all() as $metadata) {
            $resourceType = $metadata->type;
            $prefix = rtrim($this->routePrefix, '/');

            // Collection routes
            $routes->add(
                "jsonapi.{$resourceType}.index",
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
                "jsonapi.{$resourceType}.create",
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
                "jsonapi.{$resourceType}.show",
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
                "jsonapi.{$resourceType}.update",
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
                "jsonapi.{$resourceType}.delete",
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
                        "jsonapi.{$resourceType}.relationships.{$relationshipName}.show",
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
                        "jsonapi.{$resourceType}.relationships.{$relationshipName}.update",
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
                            "jsonapi.{$resourceType}.relationships.{$relationshipName}.add",
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
                            "jsonapi.{$resourceType}.relationships.{$relationshipName}.remove",
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
                        "jsonapi.{$resourceType}.related.{$relationshipName}",
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

        return $routes;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $type === 'jsonapi';
    }
}

