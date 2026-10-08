<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Contract\Resource;

/**
 * Provides metadata about a JSON:API resource type.
 *
 * Read-only resource type identity, implemented by the bundle's ResourceMetadata.
 * It does not replace the full metadata shape required by ResourceRegistryInterface.
 * Dynamic metadata registries must return the supported concrete ResourceMetadata.
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
