<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Contract\Data;

use AlexFigures\Symfony\Http\Exception\NotImplementedException;
use AlexFigures\Symfony\Query\Criteria;

/**
 * Null Object implementation of ResourceRepository.
 *
 * Used as the default implementation when the user
 * has not provided their own implementation.
 *
 * Throws NotImplementedException for all methods.
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
