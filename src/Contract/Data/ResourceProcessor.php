<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Contract\Data;

use JsonApi\Symfony\Http\Exception\ConflictException;
use JsonApi\Symfony\Http\Exception\NotFoundException;

/**
 * Processes JSON:API resource write operations.
 * 
 * This interface replaces the deprecated ResourcePersister interface.
 * The key difference is that ResourceProcessor implementations do NOT
 * call flush() - flushing is handled by WriteListener after controller execution.
 * 
 * This allows Doctrine's CommitOrderCalculator to properly order entity
 * insertions based on foreign key dependencies, solving issues with
 * hierarchical entities (tree structures, parent-child relationships).
 * 
 * Implement this interface to integrate with your data layer (Doctrine ORM, MongoDB, etc.)
 * for handling write operations (POST, PATCH, DELETE).
 *
 * Example implementation for Doctrine ORM:
 * ```php
 * final class DoctrineArticleProcessor implements ResourceProcessor
 * {
 *     public function __construct(
 *         private EntityManagerInterface $em,
 *         private FlushManager $flushManager,
 *     ) {}
 * 
 *     public function processCreate(string $type, ChangeSet $changes, ?string $clientId = null): object
 *     {
 *         $article = new Article();
 *         $article->id = $clientId ?? Uuid::v4()->toString();
 *         foreach ($changes->attributes as $key => $value) {
 *             $article->$key = $value;
 *         }
 *         
 *         $this->em->persist($article);
 *         $this->flushManager->scheduleFlush(); // Schedule flush, don't execute it
 *         
 *         return $article;
 *     }
 * }
 * ```
 *
 * @api This interface is part of the public API and follows semantic versioning.
 * @since 1.0.0
 */
interface ResourceProcessor
{
    /**
     * Process resource creation.
     * 
     * This method should:
     * 1. Create and configure the entity
     * 2. Apply attribute changes from ChangeSet
     * 3. Call EntityManager::persist()
     * 4. Schedule flush via FlushManager::scheduleFlush()
     * 5. Return the created entity
     * 
     * Do NOT call EntityManager::flush() - this is handled by WriteListener.
     *
     * @param string $type Resource type (e.g., 'articles')
     * @param ChangeSet $changes Attribute and relationship changes to apply
     * @param string|null $clientId Optional client-generated ID
     * @return object The created resource object
     * @throws ConflictException If a resource with the given client ID already exists
     */
    public function processCreate(string $type, ChangeSet $changes, ?string $clientId = null): object;

    /**
     * Process resource update.
     * 
     * This method should:
     * 1. Find the existing entity
     * 2. Apply attribute changes from ChangeSet
     * 3. Schedule flush via FlushManager::scheduleFlush()
     * 4. Return the updated entity
     * 
     * Do NOT call EntityManager::flush() - this is handled by WriteListener.
     *
     * @param string $type Resource type (e.g., 'articles')
     * @param string $id Resource identifier
     * @param ChangeSet $changes Attribute and relationship changes to apply
     * @return object The updated resource object
     * @throws NotFoundException If the resource does not exist
     */
    public function processUpdate(string $type, string $id, ChangeSet $changes): object;

    /**
     * Process resource deletion.
     * 
     * This method should:
     * 1. Find the existing entity
     * 2. Call EntityManager::remove()
     * 3. Schedule flush via FlushManager::scheduleFlush()
     * 
     * Do NOT call EntityManager::flush() - this is handled by WriteListener.
     *
     * @param string $type Resource type (e.g., 'articles')
     * @param string $id Resource identifier
     * @return void
     * @throws NotFoundException If the resource does not exist
     */
    public function processDelete(string $type, string $id): void;
}

