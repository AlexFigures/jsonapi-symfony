<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Contract\Data;

/**
 * RelationshipReader interface that supports specific resource types.
 *
 * Used by the tagging system to register per-type readers.
 */
interface TypedRelationshipReader extends RelationshipReader
{
    /**
     * Checks whether this reader supports the given resource type.
     *
     * @param string $type JSON:API resource type (for example 'articles', 'users')
     * @return bool true when the reader supports the type
     */
    public function supports(string $type): bool;
}
