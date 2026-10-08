<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Docs\Attribute;

use Attribute;

/**
 * Defines an example for an OpenAPI endpoint.
 *
 * @api This attribute is part of the public API and follows semantic versioning.
 * @since 1.0.0
 */
#[Attribute]
final readonly class OpenApiExample
{
    /**
     * @param string      $summary     Short summary of the example
     * @param mixed       $value       Example value
     * @param string|null $description Detailed description (optional)
     */
    public function __construct(
        public string $summary,
        public mixed $value,
        public ?string $description = null,
    ) {
    }
}
