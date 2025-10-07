<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Contract\Data;

use JsonApi\Symfony\Http\Exception\NotImplementedException;

/**
 * Null Object реализация ResourcePersister.
 * 
 * Используется как дефолтная реализация, когда пользователь
 * не предоставил свою реализацию.
 * 
 * Выбрасывает NotImplementedException для всех методов.
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

