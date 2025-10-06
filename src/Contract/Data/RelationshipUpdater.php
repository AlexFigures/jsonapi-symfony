<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Contract\Data;

interface RelationshipUpdater
{
    public function replaceToOne(string $type, string $id, string $rel, ?ResourceIdentifier $target): void;

    /**
     * @param list<ResourceIdentifier> $targets
     */
    public function replaceToMany(string $type, string $id, string $rel, array $targets): void;

    /**
     * @param list<ResourceIdentifier> $targets
     */
    public function addToMany(string $type, string $id, string $rel, array $targets): void;

    /**
     * @param list<ResourceIdentifier> $targets
     */
    public function removeFromToMany(string $type, string $id, string $rel, array $targets): void;
}
