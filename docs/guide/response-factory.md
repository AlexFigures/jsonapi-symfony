# JSON:API Response Factory

The `JsonApiResponseFactory` provides a fluent API for building JSON:API compliant responses in custom controllers. This is useful for endpoints that don't fit the standard CRUD or handler-based patterns, such as file uploads, webhooks, or custom integrations.

## When to Use

Use the Response Factory when:
- **File uploads** - Handling `multipart/form-data` requests
- **Webhooks** - Processing external callbacks
- **Custom integrations** - Non-standard endpoints
- **Special operations** - Actions that don't fit CRUD patterns

For standard custom routes with business logic, prefer the [handler-based approach](custom-handlers.md) which provides automatic transaction management and error handling.

## Installation

The factory is automatically registered as a service and can be injected into any controller:

```php
use AlexFigures\Symfony\Http\Response\JsonApiResponseFactory;

class MediaUploadController
{
    public function __construct(
        private readonly JsonApiResponseFactory $jsonApi,
    ) {}
}
```

## Basic Usage

### Single Resource (200 OK)

Return a single resource with default 200 OK status:

```php
#[Route('/api/media/{id}', methods: ['GET'])]
public function show(Media $media): Response
{
    return $this->jsonApi->resource('media', $media)->build();
}
```

### Created Resource (201 Created)

Return a newly created resource with 201 status and automatic Location header:

```php
#[Route('/api/media/upload', methods: ['POST'])]
public function upload(Request $request): Response
{
    $file = $request->files->get('file');
    $media = $this->mediaService->createFromUpload($file);
    
    return $this->jsonApi->created('media', $media)->build();
}
```

The Location header is automatically set to the resource's self link.

### Collection (200 OK)

Return a collection of resources:

```php
#[Route('/api/media/recent', methods: ['GET'])]
public function recent(): Response
{
    $media = $this->mediaRepository->findRecent(limit: 10);
    
    return $this->jsonApi->collection('media', $media)->build();
}
```

### No Content (204)

Return an empty response for successful operations without data:

```php
#[Route('/api/media/{id}', methods: ['DELETE'])]
public function delete(Media $media): Response
{
    $this->mediaService->delete($media);
    
    return $this->jsonApi->noContent();
}
```

### Accepted (202)

Return 202 Accepted for asynchronous operations:

```php
#[Route('/api/media/batch-process', methods: ['POST'])]
public function batchProcess(Request $request): Response
{
    $job = $this->jobService->createBatchJob($request->getContent());
    
    return $this->jsonApi->accepted()
        ->withMeta(['jobId' => $job->id, 'status' => 'pending'])
        ->withLinks(['status' => "/api/jobs/{$job->id}"])
        ->build();
}
```

## Advanced Features

### Adding Meta Information

Add custom metadata to the response:

```php
return $this->jsonApi->created('media', $media)
    ->withMeta([
        'uploadedAt' => time(),
        'size' => $media->getSize(),
        'mimeType' => $media->getMimeType(),
    ])
    ->build();
```

### Adding Custom Links

Add custom links to the response:

```php
return $this->jsonApi->resource('media', $media)
    ->withLinks([
        'thumbnail' => "/media/{$media->id}/thumbnail",
        'download' => "/media/{$media->id}/download",
    ])
    ->build();
```

### Including Related Resources

Include related resources in compound documents:

```php
return $this->jsonApi->resource('articles', $article)
    ->withInclude(['author', 'comments', 'comments.author'])
    ->build();
```

### Sparse Fieldsets

Limit which attributes are returned:

```php
return $this->jsonApi->collection('articles', $articles)
    ->withSparseFieldsets([
        'articles' => ['title', 'summary'],
        'people' => ['name'],
    ])
    ->build();
```

### Custom HTTP Headers

Add custom headers to the response:

```php
return $this->jsonApi->created('media', $media)
    ->withHeader('X-Upload-Id', $uploadId)
    ->withHeader('X-Processing-Time', $processingTime)
    ->build();
```

### Custom Status Codes

Override the default status code:

```php
return $this->jsonApi->resource('articles', $article)
    ->withStatus(206) // Partial Content
    ->build();
```

### Pagination for Collections

Specify total items for proper pagination:

```php
$media = $this->mediaRepository->findAll(limit: 20, offset: 0);
$total = $this->mediaRepository->count();

return $this->jsonApi->collection('media', $media)
    ->withTotalItems($total)
    ->build();
```

## Error Responses

### Simple Error

Return a simple error response:

```php
if ($file === null) {
    return $this->jsonApi->error(400, 'File is required')->build();
}
```

### Error with Details

Add code, title, and source information:

```php
return $this->jsonApi->error(400, 'File size exceeds maximum allowed')
    ->withCode('file_too_large')
    ->withTitle('File Too Large')
    ->withSource(pointer: '/data/attributes/file')
    ->withMeta(['maxSize' => '10MB', 'actualSize' => '15MB'])
    ->build();
```

### Validation Errors

Return multiple validation errors (422 Unprocessable Entity):

```php
return $this->jsonApi->validationErrors([
    [
        'pointer' => '/data/attributes/email',
        'detail' => 'Invalid email format',
        'code' => 'invalid_email',
    ],
    [
        'pointer' => '/data/attributes/age',
        'detail' => 'Must be at least 18',
        'code' => 'age_too_low',
    ],
])->build();
```

## Complete Example

Here's a complete example of a media upload controller:

```php
<?php

use AlexFigures\Symfony\Http\Response\JsonApiResponseFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MediaUploadController
{
    public function __construct(
        private readonly JsonApiResponseFactory $jsonApi,
        private readonly MediaService $mediaService,
    ) {}

    #[Route('/api/media/upload', methods: ['POST'])]
    public function upload(Request $request): Response
    {
        // Validate file presence
        $file = $request->files->get('file');
        if ($file === null) {
            return $this->jsonApi->error(400, 'File is required')
                ->withCode('file_required')
                ->withSource(parameter: 'file')
                ->build();
        }

        // Validate file upload
        if (!$file->isValid()) {
            return $this->jsonApi->error(400, 'Invalid file upload')
                ->withCode('invalid_upload')
                ->build();
        }

        // Validate file size
        $maxSize = 10 * 1024 * 1024; // 10MB
        if ($file->getSize() > $maxSize) {
            return $this->jsonApi->error(400, 'File size exceeds maximum allowed')
                ->withCode('file_too_large')
                ->withMeta([
                    'maxSize' => '10MB',
                    'actualSize' => round($file->getSize() / 1024 / 1024, 2) . 'MB',
                ])
                ->build();
        }

        // Process upload
        try {
            $media = $this->mediaService->createFromUpload($file);
            
            return $this->jsonApi->created('media', $media)
                ->withMeta([
                    'uploadedAt' => time(),
                    'size' => $media->getSize(),
                    'mimeType' => $media->getMimeType(),
                ])
                ->withLinks([
                    'thumbnail' => "/media/{$media->getId()}/thumbnail",
                    'download' => "/media/{$media->getId()}/download",
                ])
                ->build();
                
        } catch (ValidationException $e) {
            return $this->jsonApi->validationErrors([
                ['pointer' => '/data/attributes/file', 'detail' => $e->getMessage()],
            ])->build();
        }
    }
}
```

## API Reference

### JsonApiResponseFactory Methods

- `resource(string $type, object $resource): JsonApiResponseBuilder` - Single resource (200 OK)
- `created(string $type, object $resource): JsonApiResponseBuilder` - Created resource (201 Created)
- `collection(string $type, array $resources, ?int $totalItems = null): JsonApiResponseBuilder` - Collection (200 OK)
- `noContent(): Response` - No content (204 No Content)
- `accepted(?string $type = null, ?object $resource = null): JsonApiResponseBuilder` - Accepted (202 Accepted)
- `error(int $status, string $detail): JsonApiErrorBuilder` - Error response
- `validationErrors(array $errors): JsonApiErrorBuilder` - Validation errors (422 Unprocessable Entity)

### JsonApiResponseBuilder Methods

- `withMeta(array $meta): self` - Add top-level meta
- `withLinks(array $links): self` - Add top-level links
- `withHeader(string $name, string $value): self` - Add HTTP header
- `withStatus(int $status): self` - Override status code
- `withInclude(array $include): self` - Include related resources
- `withSparseFieldsets(array $fieldsets): self` - Sparse fieldsets
- `withTotalItems(int $totalItems): self` - Total items for pagination
- `withRequest(Request $request): self` - Provide request context
- `build(): Response` - Build final response

### JsonApiErrorBuilder Methods

- `withCode(string $code): self` - Set error code
- `withTitle(string $title): self` - Set error title
- `withSource(?string $pointer, ?string $parameter, ?string $header): self` - Set error source
- `withMeta(array $meta): self` - Add error meta
- `withLinks(array $links): self` - Add error links
- `withHeader(string $name, string $value): self` - Add HTTP header
- `build(): Response` - Build final response

## See Also

- [Custom Handlers](custom-handlers.md) - Handler-based approach for custom routes
- [Custom Routes](custom-routes.md) - Defining custom routes
- [Getting Started](getting-started.md) - Basic setup and configuration

