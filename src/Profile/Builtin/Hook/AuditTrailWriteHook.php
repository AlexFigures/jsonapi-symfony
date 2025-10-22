<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Profile\Builtin\Hook;

use AlexFigures\Symfony\Contract\Data\ChangeSet;
use AlexFigures\Symfony\Profile\Hook\WriteHook;
use AlexFigures\Symfony\Profile\ProfileContext;

/**
 * Write hook for audit trail profile.
 *
 * Automatically tracks creation and update metadata for resources.
 *
 * Usage:
 * - On create: sets createdAt timestamp and createdBy user
 * - On update: sets updatedAt timestamp and updatedBy user
 * - Requires entity to have these fields (or configure custom field names)
 *
 * @phpstan-type AuditTrailWriteConfig array{
 *     createdAtField?: string,
 *     createdByField?: string,
 *     updatedAtField?: string,
 *     updatedByField?: string,
 *     userProvider?: callable(): ?string
 * }
 */
final readonly class AuditTrailWriteHook implements WriteHook
{
    /**
     * @param AuditTrailWriteConfig $config
     */
    public function __construct(
        private array $config = []
    ) {
    }

    public function onBeforeCreate(ProfileContext $context, string $type, ChangeSet $changeSet): void
    {
        $createdAtField = $this->config['createdAtField'] ?? 'createdAt';
        $createdByField = $this->config['createdByField'] ?? 'createdBy';

        // Set createdAt if not already set
        if (!isset($changeSet->attributes[$createdAtField])) {
            $changeSet->attributes[$createdAtField] = new \DateTimeImmutable();
        }

        // Set createdBy if user provider is configured
        if (isset($this->config['userProvider']) && !isset($changeSet->attributes[$createdByField])) {
            $userId = ($this->config['userProvider'])();
            if ($userId !== null) {
                $changeSet->attributes[$createdByField] = $userId;
            }
        }
    }

    public function onBeforeUpdate(ProfileContext $context, string $type, string $id, ChangeSet $changeSet): void
    {
        $updatedAtField = $this->config['updatedAtField'] ?? 'updatedAt';
        $updatedByField = $this->config['updatedByField'] ?? 'updatedBy';

        // Always set updatedAt on update
        $changeSet->attributes[$updatedAtField] = new \DateTimeImmutable();

        // Set updatedBy if user provider is configured
        if (isset($this->config['userProvider'])) {
            $userId = ($this->config['userProvider'])();
            if ($userId !== null) {
                $changeSet->attributes[$updatedByField] = $userId;
            }
        }
    }

    public function onBeforeDelete(ProfileContext $context, string $type, string $id): void
    {
        // No action needed on delete
        // (unless you want to track deletedAt/deletedBy, which is SoftDelete's job)
    }
}
