<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Resource\Attribute;

use Attribute;

/**
 * Defines a custom route for a JSON:API resource.
 *
 * This attribute allows defining custom endpoints beyond the standard CRUD operations.
 * It can be used on entity classes or controller classes to define additional routes.
 *
 * Example usage on entity class:
 * ```php
 * #[JsonApiResource(type: 'articles')]
 * #[JsonApiCustomRoute(
 *     name: 'articles.publish',
 *     path: '/articles/{id}/publish',
 *     methods: ['POST'],
 *     controller: PublishArticleController::class
 * )]
 * final class Article
 * {
 *     // ... entity properties
 * }
 * ```
 *
 * Example usage on controller class:
 * ```php
 * #[JsonApiCustomRoute(
 *     name: 'articles.search',
 *     path: '/articles/search',
 *     methods: ['GET'],
 *     resourceType: 'articles'
 * )]
 * final class SearchArticlesController
 * {
 *     public function __invoke(Request $request): Response
 *     {
 *         // ... search logic
 *     }
 * }
 * ```
 *
 * @api This attribute is part of the public API and follows semantic versioning.
 * @since 0.2.0
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class JsonApiCustomRoute
{
    /**
     * @param string $name Route name (e.g., 'articles.publish', 'users.activate')
     * @param string $path Route path pattern (e.g., '/articles/{id}/publish', '/users/search')
     * @param array<string> $methods HTTP methods (e.g., ['POST'], ['GET', 'HEAD'])
     * @param string|null $controller Controller class name (required when used on entity classes)
     * @param string|null $resourceType Resource type (required when used on controller classes)
     * @param array<string, mixed> $defaults Additional route defaults
     * @param array<string, string> $requirements Route parameter requirements
     * @param string|null $description Optional description for documentation
     * @param int $priority Route priority (higher values = higher priority, default: 0)
     */
    public function __construct(
        public readonly string $name,
        public readonly string $path,
        public readonly array $methods = ['GET'],
        public readonly ?string $controller = null,
        public readonly ?string $resourceType = null,
        public readonly array $defaults = [],
        public readonly array $requirements = [],
        public readonly ?string $description = null,
        public readonly int $priority = 0,
    ) {
        if ($this->controller === null && $this->resourceType === null) {
            throw new \InvalidArgumentException(
                'Either controller or resourceType must be specified for JsonApiCustomRoute'
            );
        }
    }
}
