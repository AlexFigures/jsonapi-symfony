<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Contract\Data;

/**
 * Repository interface that supports specific resource types.
 *
 * Used by the tagging system to register per-type repositories.
 */
interface TypedResourceRepository extends ResourceRepository
{
    /**
     * Checks whether this repository supports the given resource type.
     *
     * @param  string $type JSON:API resource type (for example 'articles', 'users')
     * @return bool   true when the repository supports the type
     */
    public function supports(string $type): bool;
}
