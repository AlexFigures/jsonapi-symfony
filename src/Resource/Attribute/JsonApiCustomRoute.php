<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Resource\Attribute;

use Attribute;

/**
 * Defines a custom route for a JSON:API resource.
 *
 * This attribute allows defining custom endpoints beyond the standard CRUD operations.
 * It can be used on entity classes or controller classes to define additional routes.
 *
 * **NEW in 0.3.0**: Use the `handler` parameter for improved DX with automatic
 * JSON:API formatting, transaction management, and error handling.
 *
 * Example usage with handler (RECOMMENDED - new in 0.3.0):
 * ```php
 * #[JsonApiResource(type: 'articles')]
 * #[JsonApiCustomRoute(
 *     name: 'articles.publish',
 *     path: '/articles/{id}/publish',
 *     methods: ['POST'],
 *     handler: PublishArticleHandler::class
 * )]
 * final class Article
 * {
 *     // ... entity properties
 * }
 *
 * final class PublishArticleHandler implements CustomRouteHandlerInterface
 * {
 *     public function handle(CustomRouteContext $context): CustomRouteResult
 *     {
 *         $article = $context->getResource(); // Pre-loaded!
 *         $article->published = true;
 *         return CustomRouteResult::resource($article);
 *     }
 * }
 * ```
 *
 * Example usage with controller (legacy, still supported):
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
 * @api This attribute is part of the public API and follows semantic versioning.
 * @since 0.2.0
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class JsonApiCustomRoute
{
    /**
     * @param string                $name         Route name (e.g., 'articles.publish', 'users.activate')
     * @param string                $path         Route path pattern (e.g., '/articles/{id}/publish', '/users/search')
     * @param array<string>         $methods      HTTP methods (e.g., ['POST'], ['GET', 'HEAD'])
     * @param string|null           $handler      Handler class name implementing CustomRouteHandlerInterface (recommended, new in 0.3.0)
     * @param string|null           $controller   Controller class name (legacy, still supported for backward compatibility)
     * @param string|null           $resourceType Resource type (required when used on controller/handler classes)
     * @param array<string, mixed>  $defaults     Additional route defaults
     * @param array<string, string> $requirements Route parameter requirements
     * @param string|null           $description  Optional description for documentation
     * @param int                   $priority     Route priority (higher values = higher priority, default: 0)
     */
    public function __construct(
        public readonly string $name,
        public readonly string $path,
        public readonly array $methods = ['GET'],
        public readonly ?string $handler = null,
        public readonly ?string $controller = null,
        public readonly ?string $resourceType = null,
        public readonly array $defaults = [],
        public readonly array $requirements = [],
        public readonly ?string $description = null,
        public readonly int $priority = 0,
    ) {
        if ($this->handler === null && $this->controller === null && $this->resourceType === null) {
            throw new \InvalidArgumentException(
                'Either handler, controller, or resourceType must be specified for JsonApiCustomRoute'
            );
        }

        if ($this->handler !== null && $this->controller !== null) {
            throw new \InvalidArgumentException(
                'Cannot specify both handler and controller for JsonApiCustomRoute. Use handler for new code.'
            );
        }
    }
}
