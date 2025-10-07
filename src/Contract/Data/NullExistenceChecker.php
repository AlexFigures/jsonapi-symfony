<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Contract\Data;

use JsonApi\Symfony\Http\Exception\NotImplementedException;

/**
 * Null Object реализация ExistenceChecker.
 * 
 * Используется как дефолтная реализация, когда пользователь
 * не предоставил свою реализацию.
 * 
 * Выбрасывает NotImplementedException для всех методов.
 */
final class NullExistenceChecker implements ExistenceChecker
{
    public function exists(string $type, string $id): bool
    {
        throw new NotImplementedException(
            sprintf(
                'Existence checking is not implemented for type "%s". ' .
                'Please provide your own implementation of ExistenceChecker.',
                $type
            )
        );
    }
}

