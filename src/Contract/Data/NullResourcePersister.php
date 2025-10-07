<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Contract\Data;

use JsonApi\Symfony\Http\Exception\NotImplementedException;

/**
 * Null Object implementation of ResourcePersister.
 *
 * Used as the default implementation when the user
 * has not provided their own implementation.
 *
 * Throws NotImplementedException for all methods.
 */
final class NullResourcePersister implements ResourcePersister
{
    public function create(string $type, ChangeSet $changes, ?string $clientId = null): object
    {
        throw new NotImplementedException(
            sprintf(
                'Resource creation is not implemented for type "%s". ' .
                'Please provide your own implementation of ResourcePersister.',
                $type
            )
        );
    }

    public function update(string $type, string $id, ChangeSet $changes): object
    {
        throw new NotImplementedException(
            sprintf(
                'Resource update is not implemented for type "%s". ' .
                'Please provide your own implementation of ResourcePersister.',
                $type
            )
        );
    }

    public function delete(string $type, string $id): void
    {
        throw new NotImplementedException(
            sprintf(
                'Resource deletion is not implemented for type "%s". ' .
                'Please provide your own implementation of ResourcePersister.',
                $type
            )
        );
    }
}

