<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Docs\Attribute;

use Attribute;

/**
 * Registers a custom endpoint in the JSON:API OpenAPI documentation.
 *
 * Use this attribute on Symfony controller methods to include them in the
 * generated OpenAPI specification alongside JSON:API endpoints.
 *
 * Example usage:
 * ```php
 * use AlexFigures\Symfony\Docs\Attribute\OpenApiEndpoint;
 * use AlexFigures\Symfony\Docs\Attribute\OpenApiRequestBody;
 * use AlexFigures\Symfony\Docs\Attribute\OpenApiResponse;
 * use Symfony\Component\Routing\Annotation\Route;
 *
 * class UploadController extends AbstractController
 * {
 *     #[Route('/api/upload', methods: ['POST'])]
 *     #[OpenApiEndpoint(
 *         summary: 'Upload file',
 *         description: 'Upload a file using multipart/form-data',
 *         requestBody: new OpenApiRequestBody(
 *             contentType: 'multipart/form-data',
 *             schema: [
 *                 'type' => 'object',
 *                 'properties' => [
 *                     'file' => ['type' => 'string', 'format' => 'binary'],
 *                     'description' => ['type' => 'string']
 *                 ],
 *                 'required' => ['file']
 *             ]
 *         ),
 *         responses: [
 *             200 => new OpenApiResponse(
 *                 description: 'File uploaded successfully',
 *                 contentType: 'application/json',
 *                 schema: [
 *                     'type' => 'object',
 *                     'properties' => [
 *                         'url' => ['type' => 'string', 'format' => 'uri']
 *                     ]
 *                 ]
 *             ),
 *             400 => new OpenApiResponse(
 *                 description: 'Invalid request',
 *                 contentType: 'application/vnd.api+json',
 *                 schemaRef: '#/components/schemas/ErrorDocument'
 *             )
 *         ],
 *         tags: ['Upload']
 *     )]
 *     public function upload(Request $request): Response
 *     {
 *         // ...
 *     }
 * }
 * ```
 *
 * @api This attribute is part of the public API and follows semantic versioning.
 * @since 1.0.0
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class OpenApiEndpoint
{
    /**
     * @param string                                    $summary     Short summary of the endpoint
     * @param string|null                               $description Detailed description (optional)
     * @param OpenApiRequestBody|null                   $requestBody Request body specification (optional)
     * @param array<int, OpenApiResponse>               $responses   Response specifications indexed by HTTP status code
     * @param list<string>                              $tags        Tags for grouping endpoints in documentation
     * @param list<OpenApiParameter>                    $parameters  Query/path/header parameters (optional)
     * @param string|null                               $operationId Unique operation ID (auto-generated if null)
     * @param array<string, mixed>                      $security    Security requirements (optional)
     * @param bool                                      $deprecated  Mark endpoint as deprecated
     * @param array<string, OpenApiExample>             $examples    Request/response examples (optional)
     */
    public function __construct(
        public readonly string $summary,
        public readonly ?string $description = null,
        public readonly ?OpenApiRequestBody $requestBody = null,
        public readonly array $responses = [],
        public readonly array $tags = [],
        public readonly array $parameters = [],
        public readonly ?string $operationId = null,
        public readonly array $security = [],
        public readonly bool $deprecated = false,
        public readonly array $examples = [],
    ) {
    }
}

