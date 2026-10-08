<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Bridge\Symfony\Null;

use AlexFigures\JsonApi\Contract\Data\ResourceRepository;
use AlexFigures\JsonApi\Contract\Data\Slice;
use AlexFigures\JsonApi\Http\Exception\NotImplementedException;
use AlexFigures\JsonApi\Query\Criteria;

/**
 * Null Object implementation of ResourceRepository.
 *
 * Used as the default implementation when the user
 * has not provided their own implementation.
 *
 * Throws NotImplementedException for all methods.
 * @internal
 */
final class NullResourceRepository implements ResourceRepository
{
    public function findCollection(string $type, Criteria $criteria): Slice
    {
        throw new NotImplementedException(
            sprintf(
                'Resource repository is not implemented for type "%s". ' .
                'Please provide your own implementation of ResourceRepository.',
                $type
            )
        );
    }

    public function findOne(string $type, string $id, Criteria $criteria): ?object
    {
        throw new NotImplementedException(
            sprintf(
                'Resource repository is not implemented for type "%s". ' .
                'Please provide your own implementation of ResourceRepository.',
                $type
            )
        );
    }

    public function findRelated(string $type, string $relationship, array $identifiers): iterable
    {
        throw new NotImplementedException(
            sprintf(
                'Resource repository is not implemented for type "%s". ' .
                'Please provide your own implementation of ResourceRepository.',
                $type
            )
        );
    }
}
