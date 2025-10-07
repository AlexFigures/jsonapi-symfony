<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Contract\Data;

use JsonApi\Symfony\Query\Criteria;

/**
 * Reads JSON:API resources and collections from the data layer.
 *
 * Implement this interface to integrate with your data source (Doctrine ORM, MongoDB, etc.)
 * for handling read operations (GET collection, GET resource).
 *
 * Example implementation for Doctrine ORM:
 * ```php
 * final class DoctrineArticleRepository implements ResourceRepository
 * {
 *     public function findCollection(string $type, Criteria $criteria): Slice
 *     {
 *         $qb = $this->em->createQueryBuilder()
 *             ->select('a')
 *             ->from(Article::class, 'a');
 *         // Apply filters, sorting, pagination from $criteria
 *         $items = $qb->getQuery()->getResult();
 *         return new Slice($items, $criteria->pagination->number, $criteria->pagination->size, $total);
 *     }
 *
 *     public function findOne(string $type, string $id, Criteria $criteria): ?object
 *     {
 *         return $this->em->find(Article::class, $id);
 *     }
 * }
 * ```
 *
 * @api This interface is part of the public API and follows semantic versioning.
 * @since 0.1.0
 */
interface ResourceRepository
{
    /**
     * Find a collection of resources with pagination, filtering, and sorting.
     *
     * @param string $type Resource type (e.g., 'articles')
     * @param Criteria $criteria Query criteria (filters, sorting, pagination, sparse fieldsets)
     * @return Slice Paginated collection of resources
     */
    public function findCollection(string $type, Criteria $criteria): Slice;

    /**
     * Find a single resource by ID.
     *
     * @param string $type Resource type (e.g., 'articles')
     * @param string $id Resource identifier
     * @param Criteria $criteria Query criteria (sparse fieldsets, includes)
     * @return object|null Resource object or null if not found
     */
    public function findOne(string $type, string $id, Criteria $criteria): ?object;

    /**
     * Find related resources for a given relationship.
     *
     * Used to load resources referenced in relationships (e.g., loading authors for articles).
     *
     * @param string $type Target resource type (e.g., 'authors')
     * @param string $relationship Relationship name (e.g., 'author')
     * @param list<ResourceIdentifier> $identifiers Resource identifiers to load
     * @return iterable<object> Related resource objects
     */
    public function findRelated(string $type, string $relationship, array $identifiers): iterable;
}
