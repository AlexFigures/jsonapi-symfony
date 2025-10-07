<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Contract\Data;

use JsonApi\Symfony\Http\Exception\ConflictException;
use JsonApi\Symfony\Http\Exception\NotFoundException;

/**
 * Persists and removes JSON:API resources.
 *
 * Implement this interface to integrate with your data layer (Doctrine ORM, MongoDB, etc.)
 * for handling write operations (POST, PATCH, DELETE).
 *
 * Example implementation for Doctrine ORM:
 * ```php
 * final class DoctrineArticlePersister implements ResourcePersister
 * {
 *     public function create(string $type, ChangeSet $changes, ?string $clientId = null): object
 *     {
 *         $article = new Article();
 *         $article->id = $clientId ?? Uuid::v4()->toString();
 *         foreach ($changes->attributes as $key => $value) {
 *             $article->$key = $value;
 *         }
 *         $this->em->persist($article);
 *         $this->em->flush();
 *         return $article;
 *     }
 * }
 * ```
 *
 * @api This interface is part of the public API and follows semantic versioning.
 * @since 0.1.0
 */
interface ResourcePersister
{
    /**
     * Create a new resource.
     *
     * @param string $type Resource type (e.g., 'articles')
     * @param ChangeSet $changes Attribute changes to apply
     * @param string|null $clientId Optional client-generated ID
     * @return object The created resource object
     * @throws ConflictException If a resource with the given client ID already exists
     */
    public function create(string $type, ChangeSet $changes, ?string $clientId = null): object;

    /**
     * Update an existing resource.
     *
     * @param string $type Resource type (e.g., 'articles')
     * @param string $id Resource identifier
     * @param ChangeSet $changes Attribute changes to apply
     * @return object The updated resource object
     * @throws NotFoundException If the resource does not exist
     */
    public function update(string $type, string $id, ChangeSet $changes): object;

    /**
     * Delete a resource.
     *
     * @param string $type Resource type (e.g., 'articles')
     * @param string $id Resource identifier
     * @return void
     * @throws NotFoundException If the resource does not exist
     */
    public function delete(string $type, string $id): void;
}
