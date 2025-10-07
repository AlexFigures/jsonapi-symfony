<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Contract\Data;

use JsonApi\Symfony\Query\Criteria;
use JsonApi\Symfony\Query\Pagination;

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
    public function getToOneId(string $type, string $id, string $rel): ?string
    {
        return null;
    }

    public function getToManyIds(string $type, string $id, string $rel, Pagination $pagination): SliceIds
    {
        return new SliceIds([], 1, $pagination->size, 0);
    }

    public function getRelatedResource(string $type, string $id, string $rel): ?object
    {
        return null;
    }

    public function getRelatedCollection(string $type, string $id, string $rel, Criteria $criteria): Slice
    {
        return new Slice([], 1, $criteria->pagination->size, 0);
    }
}

