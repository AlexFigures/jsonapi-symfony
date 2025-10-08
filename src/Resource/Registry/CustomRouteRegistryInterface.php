<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Resource\Registry;

use JsonApi\Symfony\Resource\Metadata\CustomRouteMetadata;

interface CustomRouteRegistryInterface
{
    public function addRoute(CustomRouteMetadata $route): void;

    /**
     * @return array<CustomRouteMetadata>
     */
    public function all(): array;

    /**
     * @return array<CustomRouteMetadata>
     */
    public function getByResourceType(string $resourceType): array;
}
