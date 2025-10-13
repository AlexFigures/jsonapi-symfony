<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Contract\Data;

use AlexFigures\Symfony\Query\Criteria;
use AlexFigures\Symfony\Query\Pagination;

/**
 * Null Object implementation of RelationshipReader.
 *
 * Used as the default implementation when the user
 * has not provided their own implementation.
 *
 * Returns empty collections for all methods.
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
