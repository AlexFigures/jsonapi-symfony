<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Bridge\Symfony\Null;

use AlexFigures\Symfony\Contract\Data\RelationshipUpdater;
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
    public function setToOne(string $type, string $id, string $rel, ?string $targetId): void
    {
        throw new LogicException(
            'No RelationshipUpdater implementation found. ' .
            'To use relationship write endpoints, implement AlexFigures\Symfony\Contract\Data\RelationshipUpdater ' .
            'and register it as a service.'
        );
    }

    public function addToMany(string $type, string $id, string $rel, array $targetIds): void
    {
        throw new LogicException(
            'No RelationshipUpdater implementation found. ' .
            'To use relationship write endpoints, implement AlexFigures\Symfony\Contract\Data\RelationshipUpdater ' .
            'and register it as a service.'
        );
    }

    public function removeFromMany(string $type, string $id, string $rel, array $targetIds): void
    {
        throw new LogicException(
            'No RelationshipUpdater implementation found. ' .
            'To use relationship write endpoints, implement AlexFigures\Symfony\Contract\Data\RelationshipUpdater ' .
            'and register it as a service.'
        );
    }

    public function replaceToMany(string $type, string $id, string $rel, array $targetIds): void
    {
        throw new LogicException(
            'No RelationshipUpdater implementation found. ' .
            'To use relationship write endpoints, implement AlexFigures\Symfony\Contract\Data\RelationshipUpdater ' .
            'and register it as a service.'
        );
    }
}
