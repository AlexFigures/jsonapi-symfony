<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Contract\Data;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Represents a paginated collection of resource IDs.
 *
 * Returned by RelationshipReader::getToManyIds() to provide resource identifiers
 * for relationship linkage endpoints without loading full resource objects.
 *
 * Example usage:
 * ```php
 * $sliceIds = new SliceIds(
 *     ids: ['1', '2', '3'],
 *     pageNumber: 1,
 *     pageSize: 10,
 *     totalItems: 3,
 * );
 *
 * // Used for GET /articles/1/relationships/comments endpoint
 * // Returns: {"data": [{"type": "comments", "id": "1"}, ...]}
 * ```
 *
 * @api This class is part of the public API and follows semantic versioning.
 * @since 0.1.0
 */
final class SliceIds implements Countable, IteratorAggregate
{
    /**
     * @param list<string> $ids        Resource identifiers for the current page
     * @param int          $pageNumber Current page number (1-based)
     * @param int          $pageSize   Number of items per page
     * @param int          $totalItems Total number of items across all pages
     */
    public function __construct(
        public array $ids,
        public int $pageNumber,
        public int $pageSize,
        public int $totalItems,
    ) {
    }

    public function count(): int
    {
        return count($this->ids);
    }

    /**
     * @return Traversable<string>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->ids);
    }
}
