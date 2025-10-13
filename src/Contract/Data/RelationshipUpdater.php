<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Contract\Data;

/**
 * Updates relationships between JSON:API resources.
 *
 * Implement this interface to support relationship modification endpoints:
 * - PATCH /articles/1/relationships/author (replace to-one)
 * - PATCH /articles/1/relationships/tags (replace to-many)
 * - POST /articles/1/relationships/tags (add to to-many)
 * - DELETE /articles/1/relationships/tags (remove from to-many)
 *
 * Example implementation for Doctrine ORM:
 * ```php
 * final class DoctrineRelationshipUpdater implements RelationshipUpdater
 * {
 *     public function replaceToOne(string $type, string $id, string $rel, ?ResourceIdentifier $target): void
 *     {
 *         $article = $this->em->find(Article::class, $id);
 *         $article->author = $target ? $this->em->find(Author::class, $target->id) : null;
 *         $this->em->flush();
 *     }
 *
 *     public function addToMany(string $type, string $id, string $rel, array $targets): void
 *     {
 *         $article = $this->em->find(Article::class, $id);
 *         foreach ($targets as $target) {
 *             $tag = $this->em->find(Tag::class, $target->id);
 *             $article->tags[] = $tag;
 *         }
 *         $this->em->flush();
 *     }
 * }
 * ```
 *
 * @api This interface is part of the public API and follows semantic versioning.
 * @since 0.1.0
 */
interface RelationshipUpdater
{
    /**
     * Replace a to-one relationship.
     *
     * Used for PATCH /articles/1/relationships/author endpoint.
     *
     * @param  string                  $type   Source resource type (e.g., 'articles')
     * @param  string                  $id     Source resource identifier
     * @param  string                  $rel    Relationship name (e.g., 'author')
     * @param  ResourceIdentifier|null $target Target resource identifier or null to clear relationship
     * @return void
     */
    public function replaceToOne(string $type, string $id, string $rel, ?ResourceIdentifier $target): void;

    /**
     * Replace a to-many relationship (full replacement).
     *
     * Used for PATCH /articles/1/relationships/tags endpoint.
     *
     * @param  string                   $type    Source resource type (e.g., 'articles')
     * @param  string                   $id      Source resource identifier
     * @param  string                   $rel     Relationship name (e.g., 'tags')
     * @param  list<ResourceIdentifier> $targets Target resource identifiers
     * @return void
     */
    public function replaceToMany(string $type, string $id, string $rel, array $targets): void;

    /**
     * Add members to a to-many relationship.
     *
     * Used for POST /articles/1/relationships/tags endpoint.
     *
     * @param  string                   $type    Source resource type (e.g., 'articles')
     * @param  string                   $id      Source resource identifier
     * @param  string                   $rel     Relationship name (e.g., 'tags')
     * @param  list<ResourceIdentifier> $targets Target resource identifiers to add
     * @return void
     */
    public function addToMany(string $type, string $id, string $rel, array $targets): void;

    /**
     * Remove members from a to-many relationship.
     *
     * Used for DELETE /articles/1/relationships/tags endpoint.
     *
     * @param  string                   $type    Source resource type (e.g., 'articles')
     * @param  string                   $id      Source resource identifier
     * @param  string                   $rel     Relationship name (e.g., 'tags')
     * @param  list<ResourceIdentifier> $targets Target resource identifiers to remove
     * @return void
     */
    public function removeFromToMany(string $type, string $id, string $rel, array $targets): void;
}
