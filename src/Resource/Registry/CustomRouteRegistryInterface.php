<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Resource\Registry;

use AlexFigures\JsonApi\Resource\Metadata\CustomRouteMetadata;

/** @api */
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
