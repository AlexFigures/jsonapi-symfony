<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Contract\Data;

use JsonApi\Symfony\Http\Exception\NotImplementedException;

/**
 * Null Object реализация RelationshipUpdater.
 * 
 * Используется как дефолтная реализация, когда пользователь
 * не предоставил свою реализацию.
 * 
 * Выбрасывает NotImplementedException для всех методов.
 */
final class NullRelationshipUpdater implements RelationshipUpdater
{
    public function replaceToOne(object $resource, string $relationship, ?string $relatedId): void
    {
        throw new NotImplementedException(
            'Relationship updates are not implemented. ' .
            'Please provide your own implementation of RelationshipUpdater.'
        );
    }

    public function replaceToMany(object $resource, string $relationship, array $relatedIds): void
    {
        throw new NotImplementedException(
            'Relationship updates are not implemented. ' .
            'Please provide your own implementation of RelationshipUpdater.'
        );
    }

    public function addToMany(object $resource, string $relationship, array $relatedIds): void
    {
        throw new NotImplementedException(
            'Relationship updates are not implemented. ' .
            'Please provide your own implementation of RelationshipUpdater.'
        );
    }

    public function removeFromToMany(object $resource, string $relationship, array $relatedIds): void
    {
        throw new NotImplementedException(
            'Relationship updates are not implemented. ' .
            'Please provide your own implementation of RelationshipUpdater.'
        );
    }
}

