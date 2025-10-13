<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Contract\Data;

/**
 * Interface for RelationshipUpdater that supports specific resource types.
 *
 * Used in the tag system for registering per-type updaters.
 */
interface TypedRelationshipUpdater extends RelationshipUpdater
{
    /**
     * Checks if this updater supports the specified resource type.
     *
     * @param  string $type JSON:API resource type (e.g., 'articles', 'users')
     * @return bool   true if the updater supports this type
     */
    public function supports(string $type): bool;
}
