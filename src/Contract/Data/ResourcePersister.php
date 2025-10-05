<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Contract\Data;

use JsonApi\Symfony\Http\Exception\ConflictException;
use JsonApi\Symfony\Http\Exception\NotFoundException;

interface ResourcePersister
{
    /**
     * @throws ConflictException
     */
    public function create(string $type, ChangeSet $changes, ?string $clientId = null): object;

    /**
     * @throws NotFoundException
     */
    public function update(string $type, string $id, ChangeSet $changes): object;

    /**
     * @throws NotFoundException
     */
    public function delete(string $type, string $id): void;
}
