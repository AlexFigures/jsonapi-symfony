<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Resource\Registry;

use JsonApi\Symfony\Contract\Resource\ResourceMetadataInterface;

/**
 * @internal Stage 0 placeholder.
 */
class ResourceRegistry
{
    /**
     * @var array<string, ResourceMetadataInterface>
     */
    private array $resources = [];

    public function register(ResourceMetadataInterface $metadata): void
    {
        $this->resources[$metadata->getType()] = $metadata;
    }

    /**
     * @return array<string, ResourceMetadataInterface>
     */
    public function all(): array
    {
        return $this->resources;
    }
}
