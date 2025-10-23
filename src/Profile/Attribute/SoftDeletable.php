<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Profile\Attribute;

use Attribute;

/**
 * Marks an entity as supporting soft-delete semantics.
 *
 * This attribute is required by the SoftDeleteProfile to configure
 * which fields are used for tracking soft deletion.
 *
 * @see \AlexFigures\Symfony\Profile\Builtin\SoftDeleteProfile
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class SoftDeletable
{
    /**
     * @param string      $deletedAtField Field name for deletion timestamp (default: 'deletedAt')
     * @param string|null $deletedByField Field name for user who deleted (optional, default: null)
     */
    public function __construct(
        public readonly string $deletedAtField = 'deletedAt',
        public readonly ?string $deletedByField = null,
    ) {
    }
}
