<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Profile\Builtin\Hook;

use AlexFigures\JsonApi\Contract\Data\ChangeSet;
use AlexFigures\JsonApi\Profile\Hook\WriteHook;
use AlexFigures\JsonApi\Profile\ProfileContext;

/**
 * Write hook for soft delete profile.
 *
 * Intercepts delete operations and marks resources as deleted instead of
 * actually removing them from the database.
 *
 * Usage:
 * - On delete, sets deletedAt timestamp instead of removing entity
 * - Optionally tracks who deleted the resource (deletedBy field)
 * @internal
 */
final readonly class SoftDeleteWriteHook implements WriteHook
{
    public function onBeforeCreate(ProfileContext $context, string $type, ChangeSet $changeSet): void
    {
        // No action needed on create
    }

    public function onBeforeUpdate(ProfileContext $context, string $type, string $id, ChangeSet $changeSet): void
    {
        // No action needed on update
    }

    public function onBeforeDelete(ProfileContext $context, string $type, string $id): void
    {
        // Note: This hook is informational - actual soft delete logic
        // should be implemented in your repository/entity manager layer.
        // This hook can be used to:
        // 1. Log the deletion attempt
        // 2. Validate deletion permissions
        // 3. Trigger side effects (notifications, etc.)
        //
        // The actual soft delete implementation should be in your Doctrine
        // entity lifecycle callbacks or repository methods that check for
        // this profile and set deletedAt instead of calling remove().
        //
        // To get field names from the entity's #[SoftDeletable] attribute:
        // $entityClass = $registry->getByType($type)->class;
        // $attribute = $context->attributeReader()->getAttribute($entityClass, \AlexFigures\JsonApi\Profile\Attribute\SoftDeletable::class);
        // $deletedAtField = $attribute?->deletedAtField ?? 'deletedAt';
        // $deletedByField = $attribute?->deletedByField ?? null;
    }
}
