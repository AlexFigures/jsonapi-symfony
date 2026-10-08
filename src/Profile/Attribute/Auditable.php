<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Profile\Attribute;

use Attribute;

/**
 * Marks an entity as auditable with automatic tracking of creation and update metadata.
 *
 * This attribute is required by the AuditTrailProfile to configure
 * which fields are used for tracking audit information.
 *
 * @see \AlexFigures\JsonApi\Profile\Builtin\AuditTrailProfile
 * @api
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Auditable
{
    /**
     * @param string      $createdAtField Field name for creation timestamp (default: 'createdAt')
     * @param string      $updatedAtField Field name for update timestamp (default: 'updatedAt')
     * @param string|null $createdByField Field name for user who created (optional, default: null)
     * @param string|null $updatedByField Field name for user who updated (optional, default: null)
     */
    public function __construct(
        public string $createdAtField = 'createdAt',
        public string $updatedAtField = 'updatedAt',
        public ?string $createdByField = null,
        public ?string $updatedByField = null,
    ) {
    }
}
