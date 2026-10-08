<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Bridge\Symfony\Null;

use AlexFigures\JsonApi\Contract\Data\RelationshipReader;
use AlexFigures\JsonApi\Contract\Data\SliceIds;
use AlexFigures\JsonApi\Query\Criteria;
use AlexFigures\JsonApi\Query\Pagination;
use LogicException;

/**
 * Null implementation of RelationshipReader that throws exceptions when used.
 *
 * This is registered as the default implementation when no user implementation is provided.
 * Users must implement RelationshipReader to use relationship endpoints.
 *
 * @internal
 */
final class NullRelationshipReader implements RelationshipReader
{
    public function getToOneId(string $type, string $id, string $rel): ?string
    {
        throw new LogicException(
            'No RelationshipReader implementation found. ' .
            'To use relationship endpoints, implement AlexFigures\JsonApi\Contract\Data\RelationshipReader ' .
            'and register it as a service.'
        );
    }

    public function getToManyIds(string $type, string $id, string $rel, Pagination $pagination): SliceIds
    {
        throw new LogicException(
            'No RelationshipReader implementation found. ' .
            'To use relationship endpoints, implement AlexFigures\JsonApi\Contract\Data\RelationshipReader ' .
            'and register it as a service.'
        );
    }

    public function getRelatedResource(string $type, string $id, string $rel): ?object
    {
        throw new LogicException(
            'No RelationshipReader implementation found. ' .
            'To use relationship endpoints, implement AlexFigures\JsonApi\Contract\Data\RelationshipReader ' .
            'and register it as a service.'
        );
    }

    public function getRelatedCollection(string $type, string $id, string $rel, Criteria $criteria): \AlexFigures\JsonApi\Contract\Data\Slice
    {
        throw new LogicException(
            'No RelationshipReader implementation found. ' .
            'To use relationship endpoints, implement AlexFigures\JsonApi\Contract\Data\RelationshipReader ' .
            'and register it as a service.'
        );
    }
}
