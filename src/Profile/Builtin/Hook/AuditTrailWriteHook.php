<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Profile\Builtin\Hook;

use AlexFigures\Symfony\Contract\Data\ChangeSet;
use AlexFigures\Symfony\Profile\Attribute\Auditable;
use AlexFigures\Symfony\Profile\Hook\WriteHook;
use AlexFigures\Symfony\Profile\ProfileContext;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;

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
        private array $config = [],
        private ?ResourceRegistryInterface $registry = null,
    ) {
    }

    public function onBeforeCreate(ProfileContext $context, string $type, ChangeSet $changeSet): void
    {
        // Get field names from attribute or config
        [$createdAtField, $createdByField] = $this->getFieldNames($context, $type, 'create');

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
        // Get field names from attribute or config
        [$updatedAtField, $updatedByField] = $this->getFieldNames($context, $type, 'update');

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

    /**
     * Get field names from attribute or config.
     *
     * @return array{string, string} [timestampField, userField]
     */
    private function getFieldNames(ProfileContext $context, string $type, string $operation): array
    {
        // Try to get entity class from registry
        if ($this->registry !== null && $this->registry->hasType($type)) {
            $entityClass = $this->registry->getByType($type)->class;

            // Try to read from attribute
            $attribute = $context->attributeReader()->getAttribute($entityClass, Auditable::class);
            if ($attribute instanceof Auditable) {
                if ($operation === 'create') {
                    return [$attribute->createdAtField, $attribute->createdByField ?? 'createdBy'];
                }
                return [$attribute->updatedAtField, $attribute->updatedByField ?? 'updatedBy'];
            }
        }

        // Fallback to config
        if ($operation === 'create') {
            return [
                $this->config['createdAtField'] ?? 'createdAt',
                $this->config['createdByField'] ?? 'createdBy',
            ];
        }

        return [
            $this->config['updatedAtField'] ?? 'updatedAt',
            $this->config['updatedByField'] ?? 'updatedBy',
        ];
    }
}
