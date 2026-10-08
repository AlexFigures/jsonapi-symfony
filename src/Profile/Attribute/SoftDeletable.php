<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Profile\Attribute;

use Attribute;

/**
 * Marks an entity as supporting soft-delete semantics.
 *
 * This attribute is required by the SoftDeleteProfile to configure
 * which fields are used for tracking soft deletion.
 *
 * @see \AlexFigures\JsonApi\Profile\Builtin\SoftDeleteProfile
 * @api
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class SoftDeletable
{
    /**
     * @param string      $deletedAtField Field name for deletion timestamp (default: 'deletedAt')
     * @param string|null $deletedByField Field name for user who deleted (optional, default: null)
     */
    public function __construct(
        public string $deletedAtField = 'deletedAt',
        public ?string $deletedByField = null,
    ) {
    }
}
