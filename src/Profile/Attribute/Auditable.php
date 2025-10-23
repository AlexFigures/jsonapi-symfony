<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Profile\Attribute;

use Attribute;

/**
 * Marks an entity as auditable with automatic tracking of creation and update metadata.
 *
 * This attribute is required by the AuditTrailProfile to configure
 * which fields are used for tracking audit information.
 *
 * @see \AlexFigures\Symfony\Profile\Builtin\AuditTrailProfile
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Auditable
{
    /**
     * @param string      $createdAtField Field name for creation timestamp (default: 'createdAt')
     * @param string      $updatedAtField Field name for update timestamp (default: 'updatedAt')
     * @param string|null $createdByField Field name for user who created (optional, default: null)
     * @param string|null $updatedByField Field name for user who updated (optional, default: null)
     */
    public function __construct(
        public readonly string $createdAtField = 'createdAt',
        public readonly string $updatedAtField = 'updatedAt',
        public readonly ?string $createdByField = null,
        public readonly ?string $updatedByField = null,
    ) {
    }
}
