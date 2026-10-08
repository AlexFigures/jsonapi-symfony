<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Bridge\Symfony\Locator;

use AlexFigures\JsonApi\Contract\Data\RelationshipUpdater;
use AlexFigures\JsonApi\Contract\Data\ResourceIdentifier;
use AlexFigures\JsonApi\Contract\Data\TypedRelationshipUpdater;

/** @internal Dispatch endpoint mutations by source resource type. */
final readonly class RelationshipUpdaterLocator implements RelationshipUpdater
{
    /** @param iterable<TypedRelationshipUpdater> $updaters */
    public function __construct(private iterable $updaters, private RelationshipUpdater $fallback)
    {
    }

    public function replaceToOne(string $type, string $id, string $rel, ?ResourceIdentifier $target): void
    {
        $this->updater($type)->replaceToOne($type, $id, $rel, $target);
    }
    public function replaceToMany(string $type, string $id, string $rel, array $targets): void
    {
        $this->updater($type)->replaceToMany($type, $id, $rel, $targets);
    }
    public function addToMany(string $type, string $id, string $rel, array $targets): void
    {
        $this->updater($type)->addToMany($type, $id, $rel, $targets);
    }
    public function removeFromToMany(string $type, string $id, string $rel, array $targets): void
    {
        $this->updater($type)->removeFromToMany($type, $id, $rel, $targets);
    }
    private function updater(string $type): RelationshipUpdater
    {
        foreach ($this->updaters as $updater) {
            if ($updater->supports($type)) {
                return $updater;
            }
        }
        return $this->fallback;
    }
}
