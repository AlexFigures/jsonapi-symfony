<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Resource\Registry;

use AlexFigures\JsonApi\Resource\Metadata\ResourceMetadata;

/** @api */
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
