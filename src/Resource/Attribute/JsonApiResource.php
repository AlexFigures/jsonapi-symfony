<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Resource\Attribute;

use Attribute;

/**
 * Marks a PHP class as a JSON:API resource.
 *
 * This attribute declares that a class represents a JSON:API resource type
 * and configures its basic metadata.
 *
 * Example usage:
 * ```php
 * #[JsonApiResource(
 *     type: 'articles',
 *     routePrefix: '/api',
 *     description: 'Blog articles',
 *     exposeId: true
 * )]
 * final class Article
 * {
 *     #[Id]
 *     public string $id;
 *
 *     #[Attribute]
 *     public string $title;
 * }
 * ```
 *
 * @api This attribute is part of the public API and follows semantic versioning.
 * @since 0.1.0
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class JsonApiResource
{
    /**
     * @param string $type JSON:API resource type (e.g., 'articles', 'authors')
     * @param string|null $routePrefix Optional route prefix for this resource (defaults to global prefix)
     * @param string|null $description Optional human-readable description for documentation
     * @param bool $exposeId Whether to expose the ID in the resource document (default: true)
     */
    public function __construct(
        public readonly string $type,
        public readonly ?string $routePrefix = null,
        public readonly ?string $description = null,
        public readonly bool $exposeId = true,
    ) {
    }
}
