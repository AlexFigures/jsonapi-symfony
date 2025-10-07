<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Contract\Data;

use JsonApi\Symfony\Http\Exception\NotImplementedException;
use JsonApi\Symfony\Query\Criteria;

/**
 * Null Object реализация ResourceRepository.
 * 
 * Используется как дефолтная реализация, когда пользователь
 * не предоставил свою реализацию.
 * 
 * Выбрасывает NotImplementedException для всех методов.
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

