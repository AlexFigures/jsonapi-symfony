<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Docs\Attribute;

use Attribute;

/**
 * Defines a response header for an OpenAPI endpoint.
 *
 * @api This attribute is part of the public API and follows semantic versioning.
 * @since 1.0.0
 */
#[Attribute]
final readonly class OpenApiHeader
{
    /**
     * @param string      $description Header description
     * @param string      $type        Header type: 'string', 'integer', 'boolean'
     * @param string|null $format      Header format (e.g., 'date-time', 'uri')
     */
    public function __construct(
        public string $description,
        public string $type = 'string',
        public ?string $format = null,
    ) {
    }
}
