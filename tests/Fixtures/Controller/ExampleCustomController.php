<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Fixtures\Controller;

use AlexFigures\Symfony\Docs\Attribute\OpenApiEndpoint;
use AlexFigures\Symfony\Docs\Attribute\OpenApiParameter;
use AlexFigures\Symfony\Docs\Attribute\OpenApiRequestBody;
use AlexFigures\Symfony\Docs\Attribute\OpenApiResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Example controller demonstrating OpenApiEndpoint attribute usage.
 */
class ExampleCustomController extends AbstractController
{
    #[Route('/api/health', name: 'health_check', methods: ['GET'])]
    #[OpenApiEndpoint(
        summary: 'Health check',
        description: 'Returns the health status of the application',
        responses: [
            200 => new OpenApiResponse(
                description: 'Application is healthy',
                contentType: 'application/json',
                schema: [
                    'type' => 'object',
                    'properties' => [
                        'status' => ['type' => 'string', 'enum' => ['ok']],
                        'timestamp' => ['type' => 'string', 'format' => 'date-time'],
                    ],
                ]
            ),
        ],
        tags: ['System']
    )]
    public function health(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
            'timestamp' => (new \DateTime())->format(\DateTime::ATOM),
        ]);
    }

    #[Route('/api/upload', name: 'upload_file', methods: ['POST'])]
    #[OpenApiEndpoint(
        summary: 'Upload file',
        description: 'Upload a file using multipart/form-data',
        requestBody: new OpenApiRequestBody(
            contentType: 'multipart/form-data',
            schema: [
                'type' => 'object',
                'properties' => [
                    'file' => [
                        'type' => 'string',
                        'format' => 'binary',
                        'description' => 'The file to upload',
                    ],
                    'description' => [
                        'type' => 'string',
                        'description' => 'Optional file description',
                    ],
                ],
                'required' => ['file'],
            ],
            required: true,
            description: 'File upload data'
        ),
        responses: [
            200 => new OpenApiResponse(
                description: 'File uploaded successfully',
                contentType: 'application/json',
                schema: [
                    'type' => 'object',
                    'properties' => [
                        'url' => ['type' => 'string', 'format' => 'uri'],
                        'filename' => ['type' => 'string'],
                        'size' => ['type' => 'integer'],
                    ],
                ]
            ),
            400 => new OpenApiResponse(
                description: 'Invalid file or request',
                contentType: 'application/vnd.api+json',
                schemaRef: '#/components/schemas/ErrorDocument'
            ),
        ],
        tags: ['Upload']
    )]
    public function upload(Request $request): Response
    {
        $file = $request->files->get('file');
        if ($file === null) {
            return new JsonResponse(['error' => 'No file provided'], 400);
        }

        return new JsonResponse([
            'url' => '/uploads/' . $file->getClientOriginalName(),
            'filename' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
        ]);
    }

    #[Route('/api/search', name: 'search', methods: ['GET'])]
    #[OpenApiParameter(
        name: 'q',
        in: 'query',
        description: 'Search query',
        required: true,
        type: 'string'
    )]
    #[OpenApiParameter(
        name: 'limit',
        in: 'query',
        description: 'Maximum number of results',
        required: false,
        type: 'integer',
        example: 10
    )]
    #[OpenApiEndpoint(
        summary: 'Search resources',
        description: 'Full-text search across all resources',
        responses: [
            200 => new OpenApiResponse(
                description: 'Search results',
                contentType: 'application/json',
                schema: [
                    'type' => 'object',
                    'properties' => [
                        'results' => [
                            'type' => 'array',
                            'items' => ['type' => 'object'],
                        ],
                        'total' => ['type' => 'integer'],
                    ],
                ]
            ),
        ],
        tags: ['Search']
    )]
    public function search(Request $request): Response
    {
        $query = $request->query->get('q');
        $limit = $request->query->getInt('limit', 10);

        return new JsonResponse([
            'results' => [],
            'total' => 0,
        ]);
    }
}

