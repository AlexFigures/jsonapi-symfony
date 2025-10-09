<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Bridge\Symfony\Null;

use JsonApi\Symfony\Contract\Data\ChangeSet;
use JsonApi\Symfony\Contract\Data\ResourceProcessor;
use LogicException;

/**
 * Null implementation of ResourceProcessor that throws exceptions when used.
 *
 * This is registered as the default implementation when no user implementation is provided.
 * Users must implement ResourceProcessor to use write endpoints.
 *
 * @internal
 */
final class NullResourceProcessor implements ResourceProcessor
{
    public function processCreate(string $type, ChangeSet $changes, ?string $clientId = null): object
    {
        throw new LogicException(
            'No ResourceProcessor implementation found. ' .
            'To use write endpoints (POST, PATCH, DELETE), implement JsonApi\Symfony\Contract\Data\ResourceProcessor ' .
            'and register it as a service.'
        );
    }

    public function processUpdate(string $type, string $id, ChangeSet $changes): object
    {
        throw new LogicException(
            'No ResourceProcessor implementation found. ' .
            'To use write endpoints (POST, PATCH, DELETE), implement JsonApi\Symfony\Contract\Data\ResourceProcessor ' .
            'and register it as a service.'
        );
    }

    public function processDelete(string $type, string $id): void
    {
        throw new LogicException(
            'No ResourceProcessor implementation found. ' .
            'To use write endpoints (POST, PATCH, DELETE), implement JsonApi\Symfony\Contract\Data\ResourceProcessor ' .
            'and register it as a service.'
        );
    }
}

