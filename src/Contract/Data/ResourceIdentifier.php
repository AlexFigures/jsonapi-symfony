<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Contract\Data;

/**
 * Represents a JSON:API resource identifier (type + id pair).
 *
 * Used throughout the bundle to reference resources without loading them.
 * Corresponds to the JSON:API resource identifier object: `{"type": "articles", "id": "1"}`.
 *
 * Example usage:
 * ```php
 * $identifier = new ResourceIdentifier(type: 'articles', id: '123');
 *
 * // Used in relationship updates
 * $updater->replaceToOne('articles', '1', 'author', $identifier);
 * ```
 *
 * @api This class is part of the public API and follows semantic versioning.
 * @since 0.1.0
 */
final class ResourceIdentifier
{
    /**
     * @param string $type Resource type (e.g., 'articles')
     * @param string $id Resource identifier
     */
    public function __construct(
        public string $type,
        public string $id,
    ) {
    }
}
