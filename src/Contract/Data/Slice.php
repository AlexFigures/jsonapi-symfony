<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Contract\Data;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Represents a paginated collection of resources.
 *
 * Returned by ResourceRepository::findCollection() and RelationshipReader::getRelatedCollection()
 * to provide both the resource objects and pagination metadata.
 *
 * Example usage:
 * ```php
 * $slice = new Slice(
 *     items: [$article1, $article2, $article3],
 *     pageNumber: 2,
 *     pageSize: 10,
 *     totalItems: 42,
 * );
 *
 * // The bundle uses this to generate pagination links
 * // GET /articles?page[number]=2&page[size]=10
 * ```
 *
 * @api This class is part of the public API and follows semantic versioning.
 * @since 0.1.0
 *
 * @implements IteratorAggregate<int, object>
 */
final class Slice implements Countable, IteratorAggregate
{
    /**
     * @param list<object> $items      Resource objects for the current page
     * @param int          $pageNumber Current page number (1-based)
     * @param int          $pageSize   Number of items per page
     * @param int          $totalItems Total number of items across all pages
     */
    public function __construct(
        public array $items,
        public int $pageNumber,
        public int $pageSize,
        public int $totalItems,
    ) {
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return Traversable<object>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}
