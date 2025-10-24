<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Docs\OpenApi;

use AlexFigures\Symfony\Docs\Attribute\OpenApiEndpoint;

/**
 * Metadata for a custom endpoint to be included in OpenAPI spec.
 *
 * @internal
 */
final class CustomEndpointMetadata
{
    /**
     * @param string          $path    Route path
     * @param string          $method  HTTP method
     * @param OpenApiEndpoint $openApi OpenAPI metadata
     */
    public function __construct(
        public readonly string $path,
        public readonly string $method,
        public readonly OpenApiEndpoint $openApi,
    ) {
    }
}
