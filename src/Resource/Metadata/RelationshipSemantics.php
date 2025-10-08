<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Resource\Metadata;

/**
 * Defines how to-many relationship updates are applied.
 */
enum RelationshipSemantics: string
{
    /**
     * Replace entire collection (clear + add all).
     * Simple but can be expensive and lose semantic meaning.
     */
    case REPLACE = 'replace';

    /**
     * Merge changes using diff semantics (add/remove only what changed).
     * More efficient and preserves semantic meaning of partial updates.
     */
    case MERGE = 'merge';
}
