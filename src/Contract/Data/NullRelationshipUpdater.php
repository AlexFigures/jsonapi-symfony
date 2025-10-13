<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Contract\Data;

use AlexFigures\Symfony\Http\Exception\NotImplementedException;

/**
 * Null Object implementation of RelationshipUpdater.
 *
 * Used as the default implementation when the user
 * has not provided their own implementation.
 *
 * Throws NotImplementedException for all methods.
 */
final class NullRelationshipUpdater implements RelationshipUpdater
{
    public function replaceToOne(string $type, string $id, string $rel, ?ResourceIdentifier $target): void
    {
        throw new NotImplementedException(
            'Relationship updates are not implemented. ' .
            'Please provide your own implementation of RelationshipUpdater.'
        );
    }

    public function replaceToMany(string $type, string $id, string $rel, array $targets): void
    {
        throw new NotImplementedException(
            'Relationship updates are not implemented. ' .
            'Please provide your own implementation of RelationshipUpdater.'
        );
    }

    public function addToMany(string $type, string $id, string $rel, array $targets): void
    {
        throw new NotImplementedException(
            'Relationship updates are not implemented. ' .
            'Please provide your own implementation of RelationshipUpdater.'
        );
    }

    public function removeFromToMany(string $type, string $id, string $rel, array $targets): void
    {
        throw new NotImplementedException(
            'Relationship updates are not implemented. ' .
            'Please provide your own implementation of RelationshipUpdater.'
        );
    }
}
