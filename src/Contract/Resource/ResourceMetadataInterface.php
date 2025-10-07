<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Contract\Resource;

/**
 * Provides metadata about a JSON:API resource type.
 *
 * This interface is rarely implemented directly by users. The bundle provides
 * a default implementation based on #[JsonApiResource] attributes.
 *
 * Custom implementations can be used for advanced scenarios like:
 * - Dynamic resource types
 * - Runtime resource configuration
 * - Integration with external metadata sources
 *
 * Example custom implementation:
 * ```php
 * final class DynamicResourceMetadata implements ResourceMetadataInterface
 * {
 *     public function __construct(private string $type) {}
 *
 *     public function getType(): string
 *     {
 *         return $this->type;
 *     }
 * }
 * ```
 *
 * @api This interface is part of the public API and follows semantic versioning.
 * @since 0.1.0
 */
interface ResourceMetadataInterface
{
    /**
     * Get the JSON:API resource type.
     *
     * @return string Resource type (e.g., 'articles', 'authors')
     */
    public function getType(): string;
}
