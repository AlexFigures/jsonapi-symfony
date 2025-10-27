# Custom Endpoints in OpenAPI Documentation

This guide explains how to include custom Symfony controller endpoints in the JSON:API OpenAPI documentation.

## Overview

By default, the JSON:API bundle generates OpenAPI documentation for all JSON:API resources and their standard CRUD endpoints. However, you may have custom endpoints that don't follow the JSON:API specification but should still appear in the same documentation.

The `#[OpenApiEndpoint]` attribute allows you to annotate any Symfony controller method to include it in the generated OpenAPI specification.

## Basic Usage

```php
<?php

namespace App\Controller;

use AlexFigures\Symfony\Docs\Attribute\OpenApiEndpoint;
use AlexFigures\Symfony\Docs\Attribute\OpenApiResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HealthController extends AbstractController
{
    #[Route('/api/health', methods: ['GET'])]
    #[OpenApiEndpoint(
        summary: 'Health check endpoint',
        description: 'Returns the health status of the application',
        responses: [
            200 => new OpenApiResponse(
                description: 'Application is healthy',
                contentType: 'application/json',
                schema: [
                    'type' => 'object',
                    'properties' => [
                        'status' => ['type' => 'string', 'enum' => ['ok']],
                        'timestamp' => ['type' => 'string', 'format' => 'date-time']
                    ]
                ]
            )
        ],
        tags: ['System']
    )]
    public function health(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
            'timestamp' => (new \DateTime())->format(\DateTime::ATOM)
        ]);
    }
}
```

## File Upload Example (multipart/form-data)

```php
<?php

namespace App\Controller;

use AlexFigures\Symfony\Docs\Attribute\OpenApiEndpoint;
use AlexFigures\Symfony\Docs\Attribute\OpenApiRequestBody;
use AlexFigures\Symfony\Docs\Attribute\OpenApiResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UploadController extends AbstractController
{
    #[Route('/api/upload', methods: ['POST'])]
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
                        'description' => 'The file to upload'
                    ],
                    'description' => [
                        'type' => 'string',
                        'description' => 'Optional file description'
                    ]
                ],
                'required' => ['file']
            ],
            required: true
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
                        'size' => ['type' => 'integer']
                    ]
                ]
            ),
            400 => new OpenApiResponse(
                description: 'Invalid file or request',
                contentType: 'application/vnd.api+json',
                schemaRef: '#/components/schemas/ErrorDocument'
            )
        ],
        tags: ['Upload']
    )]
    public function upload(Request $request): Response
    {
        $file = $request->files->get('file');
        // ... handle upload
        
        return new JsonResponse([
            'url' => '/uploads/' . $file->getClientOriginalName(),
            'filename' => $file->getClientOriginalName(),
            'size' => $file->getSize()
        ]);
    }
}
```

## Using Query Parameters

```php
<?php

namespace App\Controller;

use AlexFigures\Symfony\Docs\Attribute\OpenApiEndpoint;
use AlexFigures\Symfony\Docs\Attribute\OpenApiParameter;
use AlexFigures\Symfony\Docs\Attribute\OpenApiResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SearchController extends AbstractController
{
    #[Route('/api/search', methods: ['GET'])]
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
                            'items' => ['type' => 'object']
                        ],
                        'total' => ['type' => 'integer']
                    ]
                ]
            )
        ],
        tags: ['Search']
    )]
    public function search(Request $request): Response
    {
        $query = $request->query->get('q');
        $limit = $request->query->getInt('limit', 10);
        
        // ... perform search
        
        return new JsonResponse([
            'results' => [],
            'total' => 0
        ]);
    }
}
```

## Using Path Parameters

```php
#[Route('/api/articles/{id}/publish', methods: ['POST'])]
#[OpenApiEndpoint(
    summary: 'Publish article',
    description: 'Change article status to published',
    responses: [
        200 => new OpenApiResponse(
            description: 'Article published',
            contentType: 'application/vnd.api+json',
            schemaRef: '#/components/schemas/ArticleResourceDocument'
        ),
        404 => new OpenApiResponse(
            description: 'Article not found',
            contentType: 'application/vnd.api+json',
            schemaRef: '#/components/schemas/ErrorDocument'
        )
    ],
    tags: ['articles']
)]
public function publish(string $id): Response
{
    // ... publish article
}
```

## Referencing JSON:API Schemas

You can reference schemas generated for JSON:API resources:

```php
#[OpenApiResponse(
    description: 'Success',
    contentType: 'application/vnd.api+json',
    schemaRef: '#/components/schemas/ArticleResourceDocument'
)]
```

Available schema references:
- `#/components/schemas/{Type}Resource` - Resource object
- `#/components/schemas/{Type}ResourceDocument` - Single resource document
- `#/components/schemas/{Type}CollectionDocument` - Collection document
- `#/components/schemas/ErrorDocument` - Error document

Where `{Type}` is the StudlyCase version of your resource type (e.g., `Article` for `articles`).

## Complete Example

See the full example in `tests/Fixtures/Controller/ExampleCustomController.php`.

## Best Practices

1. **Use descriptive summaries** - Keep summaries short (< 50 chars)
2. **Add detailed descriptions** - Explain what the endpoint does
3. **Define all responses** - Include success and error responses
4. **Use schema references** - Reuse JSON:API schemas when possible
5. **Group with tags** - Use tags to organize endpoints in documentation
6. **Add examples** - Help API consumers understand the format

## Viewing Documentation

After adding `#[OpenApiEndpoint]` attributes, your custom endpoints will appear in:

- Swagger UI: `/api/docs`
- OpenAPI JSON: `/api/docs.json`

The endpoints will be grouped by tags and appear alongside JSON:API endpoints.

