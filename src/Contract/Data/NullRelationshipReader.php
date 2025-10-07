<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Contract\Data;

/**
 * Null Object реализация RelationshipReader.
 * 
 * Используется как дефолтная реализация, когда пользователь
 * не предоставил свою реализацию.
 * 
 * Возвращает пустые коллекции для всех методов.
 */
final class NullRelationshipReader implements RelationshipReader
{
    public function getToOneId(object $resource, string $relationship): ?string
    {
        return null;
    }

    public function getToManyIds(object $resource, string $relationship): array
    {
        return [];
    }

    public function getRelatedResource(object $resource, string $relationship): ?object
    {
        return null;
    }

    public function getRelatedCollection(object $resource, string $relationship): iterable
    {
        return [];
    }
}

