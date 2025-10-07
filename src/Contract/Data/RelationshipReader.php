<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Contract\Data;

use JsonApi\Symfony\Query\Criteria;
use JsonApi\Symfony\Query\Pagination;

/**
 * Reads relationship data and related resources.
 *
 * Implement this interface to support relationship endpoints:
 * - GET /articles/1/relationships/author (resource linkage)
 * - GET /articles/1/author (related resource)
 *
 * Example implementation for Doctrine ORM:
 * ```php
 * final class DoctrineRelationshipReader implements RelationshipReader
 * {
 *     public function getToOneId(string $type, string $id, string $rel): ?string
 *     {
 *         $article = $this->em->find(Article::class, $id);
 *         return $article?->author?->id;
 *     }
 *
 *     public function getRelatedResource(string $type, string $id, string $rel): ?object
 *     {
 *         $article = $this->em->find(Article::class, $id);
 *         return $article?->author;
 *     }
 * }
 * ```
 *
 * @api This interface is part of the public API and follows semantic versioning.
 * @since 0.1.0
 */
interface RelationshipReader
{
    /**
     * Get the ID of a to-one relationship target.
     *
     * Used for GET /articles/1/relationships/author endpoint.
     *
     * @param string $type Source resource type (e.g., 'articles')
     * @param string $id Source resource identifier
     * @param string $rel Relationship name (e.g., 'author')
     * @return string|null Target resource ID or null if relationship is empty
     */
    public function getToOneId(string $type, string $id, string $rel): ?string;

    /**
     * Get the IDs of a to-many relationship targets.
     *
     * Used for GET /articles/1/relationships/comments endpoint.
     *
     * @param string $type Source resource type (e.g., 'articles')
     * @param string $id Source resource identifier
     * @param string $rel Relationship name (e.g., 'comments')
     * @param Pagination $pagination Pagination parameters
     * @return SliceIds Paginated collection of target resource IDs
     */
    public function getToManyIds(string $type, string $id, string $rel, Pagination $pagination): SliceIds;

    /**
     * Get the related resource for a to-one relationship.
     *
     * Used for GET /articles/1/author endpoint.
     *
     * @param string $type Source resource type (e.g., 'articles')
     * @param string $id Source resource identifier
     * @param string $rel Relationship name (e.g., 'author')
     * @return object|null Related resource object or null if relationship is empty
     */
    public function getRelatedResource(string $type, string $id, string $rel): ?object;

    /**
     * Get the related resources for a to-many relationship.
     *
     * Used for GET /articles/1/comments endpoint.
     *
     * @param string $type Source resource type (e.g., 'articles')
     * @param string $id Source resource identifier
     * @param string $rel Relationship name (e.g., 'comments')
     * @param Criteria $criteria Query criteria (filters, sorting, pagination)
     * @return Slice Paginated collection of related resources
     */
    public function getRelatedCollection(string $type, string $id, string $rel, Criteria $criteria): Slice;
}
