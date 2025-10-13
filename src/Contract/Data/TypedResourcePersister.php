<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Contract\Data;

/**
 * Persister interface that supports specific resource types.
 *
 * Used by the tagging system to register per-type persisters.
 */
interface TypedResourcePersister extends ResourcePersister
{
    /**
     * Checks whether this persister supports the given resource type.
     *
     * @param  string $type JSON:API resource type (for example 'articles', 'users')
     * @return bool   true when the persister supports the type
     */
    public function supports(string $type): bool;
}
