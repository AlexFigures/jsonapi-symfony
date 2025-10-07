<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Contract\Data;

use JsonApi\Symfony\Http\Exception\NotImplementedException;

/**
 * Null Object implementation of ExistenceChecker.
 *
 * Used as the default implementation when the user
 * has not provided their own implementation.
 *
 * Throws NotImplementedException for all methods.
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

