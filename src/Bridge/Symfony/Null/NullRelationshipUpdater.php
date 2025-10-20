<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Bridge\Symfony\Null;

use AlexFigures\Symfony\Contract\Data\RelationshipUpdater;
use AlexFigures\Symfony\Contract\Data\ResourceIdentifier;
use LogicException;

/**
 * Null implementation of RelationshipUpdater that throws exceptions when used.
 *
 * This is registered as the default implementation when no user implementation is provided.
 * Users must implement RelationshipUpdater to use relationship write endpoints.
 *
 * @internal
 */
final class NullRelationshipUpdater implements RelationshipUpdater
{
    public function replaceToOne(string $type, string $id, string $rel, ?ResourceIdentifier $target): void
    {
        $this->throwMissingImplementation();
    }

    public function addToMany(string $type, string $id, string $rel, array $targets): void
    {
        $this->throwMissingImplementation();
    }

    public function removeFromToMany(string $type, string $id, string $rel, array $targets): void
    {
        $this->throwMissingImplementation();
    }

    public function replaceToMany(string $type, string $id, string $rel, array $targets): void
    {
        $this->throwMissingImplementation();
    }

    private function throwMissingImplementation(): never
    {
        throw new LogicException(
            'No RelationshipUpdater implementation found. ' .
            'To use relationship write endpoints, implement AlexFigures\Symfony\Contract\Data\RelationshipUpdater ' .
            'and register it as a service.'
        );
    }
}
