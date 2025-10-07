<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Bridge\Symfony\Null;

use JsonApi\Symfony\Contract\Data\ChangeSet;
use JsonApi\Symfony\Contract\Data\ResourcePersister;
use LogicException;

/**
 * Null implementation of ResourcePersister that throws exceptions when used.
 *
 * This is registered as the default implementation when no user implementation is provided.
 * Users must implement ResourcePersister to use write endpoints.
 *
 * @internal
 */
final class NullResourcePersister implements ResourcePersister
{
    public function create(string $type, ChangeSet $changes, ?string $clientId = null): object
    {
        throw new LogicException(
            'No ResourcePersister implementation found. ' .
            'To use write endpoints (POST, PATCH, DELETE), implement JsonApi\Symfony\Contract\Data\ResourcePersister ' .
            'and register it as a service.'
        );
    }

    public function update(string $type, string $id, ChangeSet $changes): object
    {
        throw new LogicException(
            'No ResourcePersister implementation found. ' .
            'To use write endpoints (POST, PATCH, DELETE), implement JsonApi\Symfony\Contract\Data\ResourcePersister ' .
            'and register it as a service.'
        );
    }

    public function delete(string $type, string $id): void
    {
        throw new LogicException(
            'No ResourcePersister implementation found. ' .
            'To use write endpoints (POST, PATCH, DELETE), implement JsonApi\Symfony\Contract\Data\ResourcePersister ' .
            'and register it as a service.'
        );
    }
}

