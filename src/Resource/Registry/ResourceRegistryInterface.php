<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Resource\Registry;

use JsonApi\Symfony\Resource\Metadata\ResourceMetadata;

interface ResourceRegistryInterface
{
    public function getByType(string $type): ResourceMetadata;

    public function hasType(string $type): bool;

    public function getByClass(string $class): ?ResourceMetadata;

    /**
     * @return list<ResourceMetadata>
     */
    public function all(): array;
}
