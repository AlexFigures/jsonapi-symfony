<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Docs\Attribute;

use Attribute;

/**
 * Defines the request body for an OpenAPI endpoint.
 *
 * @api This attribute is part of the public API and follows semantic versioning.
 * @since 1.0.0
 */
#[Attribute]
final class OpenApiRequestBody
{
    /**
     * @param string              $contentType Content type (e.g., 'application/json', 'multipart/form-data')
     * @param array<string, mixed> $schema      OpenAPI schema definition
     * @param bool                $required    Whether the request body is required
     * @param string|null         $description Description of the request body
     */
    public function __construct(
        public readonly string $contentType,
        public readonly array $schema,
        public readonly bool $required = true,
        public readonly ?string $description = null,
    ) {
    }
}

