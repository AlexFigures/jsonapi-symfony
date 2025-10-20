<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Contract\Data;

use AlexFigures\Symfony\Http\Exception\ConflictException;
use AlexFigures\Symfony\Http\Exception\NotFoundException;

/**
 * Legacy write contract for JSON:API resource persistence.
 *
 * @api This interface is part of the public API and follows semantic versioning.
 * @since 0.1.0
 */
interface ResourcePersister
{
    /**
     * Persist a newly created resource and return the hydrated model.
     *
     * @throws ConflictException when the provided client identifier already exists.
     */
    public function create(string $type, ChangeSet $changes, ?string $clientId = null): object;

    /**
     * Persist attribute and relationship updates for an existing resource.
     *
     * @throws NotFoundException when the resource cannot be located.
     */
    public function update(string $type, string $id, ChangeSet $changes): object;

    /**
     * Delete an existing resource.
     *
     * @throws NotFoundException when the resource cannot be located.
     */
    public function delete(string $type, string $id): void;
}
