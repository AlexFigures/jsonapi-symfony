<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Docs\Attribute;

use Attribute;

/**
 * Defines a response header for an OpenAPI endpoint.
 *
 * @api This attribute is part of the public API and follows semantic versioning.
 * @since 1.0.0
 */
#[Attribute]
final class OpenApiHeader
{
    /**
     * @param string      $description Header description
     * @param string      $type        Header type: 'string', 'integer', 'boolean'
     * @param string|null $format      Header format (e.g., 'date-time', 'uri')
     */
    public function __construct(
        public readonly string $description,
        public readonly string $type = 'string',
        public readonly ?string $format = null,
    ) {
    }
}

