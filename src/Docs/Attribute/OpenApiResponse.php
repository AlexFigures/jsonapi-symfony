<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Docs\Attribute;

use Attribute;

/**
 * Defines a response for an OpenAPI endpoint.
 *
 * @api This attribute is part of the public API and follows semantic versioning.
 * @since 1.0.0
 */
#[Attribute]
final class OpenApiResponse
{
    /**
     * @param string                            $description Description of the response
     * @param string|null                       $contentType Content type (e.g., 'application/json', 'application/vnd.api+json')
     * @param array<string, mixed>|null         $schema      OpenAPI schema definition (mutually exclusive with schemaRef)
     * @param string|null                       $schemaRef   Reference to a schema (e.g., '#/components/schemas/ErrorDocument')
     * @param array<string, OpenApiHeader>|null $headers     Response headers
     */
    public function __construct(
        public readonly string $description,
        public readonly ?string $contentType = null,
        public readonly ?array $schema = null,
        public readonly ?string $schemaRef = null,
        public readonly ?array $headers = null,
    ) {
        if ($schema !== null && $schemaRef !== null) {
            throw new \InvalidArgumentException('Cannot specify both schema and schemaRef');
        }
    }
}
