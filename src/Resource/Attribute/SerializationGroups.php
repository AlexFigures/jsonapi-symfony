<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Resource\Attribute;

/**
 * Attribute for specifying serialization groups for a resource attribute.
 *
 * Allows controlling when an attribute is available for reading/writing:
 * - read: attribute is included in response (GET, POST, PATCH)
 * - write: attribute can be modified (POST, PATCH)
 * - create: attribute can only be set during creation (POST)
 * - update: attribute can only be modified during update (PATCH)
 *
 * Examples:
 *
 * ```php
 * // Read-only (e.g., createdAt)
 * #[Attribute]
 * #[SerializationGroups(['read'])]
 * private \DateTimeInterface $createdAt;
 *
 * // Write-only (e.g., password)
 * #[Attribute]
 * #[SerializationGroups(['write'])]
 * private string $password;
 *
 * // Can only be set during creation
 * #[Attribute]
 * #[SerializationGroups(['read', 'create'])]
 * private string $slug;
 *
 * // Regular attribute (read and write)
 * #[Attribute]
 * #[SerializationGroups(['read', 'write'])]
 * private string $title;
 * ```
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
final readonly class SerializationGroups
{
    /**
     * @param array<string> $groups Serialization groups
     */
    public function __construct(
        public array $groups = [],
    ) {
    }

    public function isReadable(): bool
    {
        return in_array('read', $this->groups, true);
    }

    public function isWritable(): bool
    {
        return in_array('write', $this->groups, true);
    }

    public function isCreatable(): bool
    {
        return in_array('create', $this->groups, true);
    }

    public function isUpdatable(): bool
    {
        return in_array('update', $this->groups, true);
    }

    public function canRead(): bool
    {
        return $this->isReadable();
    }

    public function canWrite(bool $isCreate): bool
    {
        // If 'write' group exists, can always write
        if ($this->isWritable()) {
            return true;
        }

        // If creating and 'create' group exists
        if ($isCreate && $this->isCreatable()) {
            return true;
        }

        // If updating and 'update' group exists
        if (!$isCreate && $this->isUpdatable()) {
            return true;
        }

        return false;
    }
}

