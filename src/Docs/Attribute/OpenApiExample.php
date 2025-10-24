<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Docs\Attribute;

use Attribute;

/**
 * Defines an example for an OpenAPI endpoint.
 *
 * @api This attribute is part of the public API and follows semantic versioning.
 * @since 1.0.0
 */
#[Attribute]
final class OpenApiExample
{
    /**
     * @param string      $summary     Short summary of the example
     * @param mixed       $value       Example value
     * @param string|null $description Detailed description (optional)
     */
    public function __construct(
        public readonly string $summary,
        public readonly mixed $value,
        public readonly ?string $description = null,
    ) {
    }
}
