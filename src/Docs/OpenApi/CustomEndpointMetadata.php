<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Docs\OpenApi;

use AlexFigures\JsonApi\Docs\Attribute\OpenApiEndpoint;

/**
 * Metadata for a custom endpoint to be included in OpenAPI spec.
 *
 * @internal
 */
final readonly class CustomEndpointMetadata
{
    /**
     * @param string          $path    Route path
     * @param string          $method  HTTP method
     * @param OpenApiEndpoint $openApi OpenAPI metadata
     */
    public function __construct(
        public string $path,
        public string $method,
        public OpenApiEndpoint $openApi,
    ) {
    }
}
