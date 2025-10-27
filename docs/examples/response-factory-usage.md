# Response Factory Usage Examples

This document provides practical examples of using the `JsonApiResponseFactory` in various scenarios.

## Example 1: File Upload Controller

A complete example of handling file uploads with validation and JSON:API responses:

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use AlexFigures\Symfony\Http\Response\JsonApiResponseFactory;
use App\Entity\Media;
use App\Service\MediaService;
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
                ->withMeta(['hint' => 'Use multipart/form-data with "file" field'])
                ->build();
        }

        // Validate file upload
        if (!$file->isValid()) {
            return $this->jsonApi->error(400, 'Invalid file upload')
                ->withCode('invalid_upload')
                ->withMeta(['uploadError' => $file->getErrorMessage()])
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

        // Validate MIME type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedTypes, true)) {
            return $this->jsonApi->error(400, 'Invalid file type')
                ->withCode('invalid_mime_type')
                ->withMeta([
                    'allowedTypes' => $allowedTypes,
                    'actualType' => $file->getMimeType(),
                ])
                ->build();
        }

        // Process upload
        try {
            $media = $this->mediaService->createFromUpload($file);
            
            return $this->jsonApi->created('media', $media)
                ->withMeta([
                    'uploadedAt' => $media->getCreatedAt()->format('c'),
                    'size' => $media->getSize(),
                    'mimeType' => $media->getMimeType(),
                    'dimensions' => [
                        'width' => $media->getWidth(),
                        'height' => $media->getHeight(),
                    ],
                ])
                ->withLinks([
                    'thumbnail' => "/api/media/{$media->getId()}/thumbnail",
                    'download' => "/api/media/{$media->getId()}/download",
                    'preview' => "/api/media/{$media->getId()}/preview",
                ])
                ->build();
                
        } catch (\Exception $e) {
            return $this->jsonApi->error(500, 'Failed to process upload')
                ->withCode('upload_processing_failed')
                ->withMeta(['error' => $e->getMessage()])
                ->build();
        }
    }
}
```

## Example 2: Webhook Handler

Handling external webhooks with async processing:

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use AlexFigures\Symfony\Http\Response\JsonApiResponseFactory;
use App\Service\WebhookProcessor;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class WebhookController
{
    public function __construct(
        private readonly JsonApiResponseFactory $jsonApi,
        private readonly WebhookProcessor $processor,
    ) {}

    #[Route('/webhooks/stripe', methods: ['POST'])]
    public function stripeWebhook(Request $request): Response
    {
        // Verify signature
        $signature = $request->headers->get('Stripe-Signature');
        if (!$this->processor->verifySignature($request->getContent(), $signature)) {
            return $this->jsonApi->error(401, 'Invalid signature')
                ->withCode('invalid_signature')
                ->build();
        }

        // Parse event
        $payload = json_decode($request->getContent(), true);
        if ($payload === null) {
            return $this->jsonApi->error(400, 'Invalid JSON payload')
                ->withCode('invalid_json')
                ->build();
        }

        // Queue for async processing
        $job = $this->processor->queueWebhook($payload);

        // Return 202 Accepted
        return $this->jsonApi->accepted()
            ->withMeta([
                'jobId' => $job->getId(),
                'status' => 'queued',
                'eventType' => $payload['type'] ?? 'unknown',
                'queuedAt' => time(),
            ])
            ->withLinks([
                'status' => "/api/jobs/{$job->getId()}",
            ])
            ->withHeader('X-Job-Id', $job->getId())
            ->build();
    }

    #[Route('/webhooks/github', methods: ['POST'])]
    public function githubWebhook(Request $request): Response
    {
        $event = $request->headers->get('X-GitHub-Event');
        
        // Process synchronously for simple events
        if ($event === 'ping') {
            return $this->jsonApi->accepted()
                ->withMeta(['message' => 'pong', 'event' => 'ping'])
                ->build();
        }

        // Queue complex events
        $job = $this->processor->queueWebhook([
            'event' => $event,
            'payload' => json_decode($request->getContent(), true),
        ]);

        return $this->jsonApi->accepted()
            ->withMeta([
                'jobId' => $job->getId(),
                'event' => $event,
            ])
            ->build();
    }
}
```

## Example 3: Custom Search Endpoint

Custom search with collection response:

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use AlexFigures\Symfony\Http\Response\JsonApiResponseFactory;
use App\Repository\ArticleRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SearchController
{
    public function __construct(
        private readonly JsonApiResponseFactory $jsonApi,
        private readonly ArticleRepository $articleRepository,
    ) {}

    #[Route('/api/articles/search', methods: ['GET'])]
    public function search(Request $request): Response
    {
        $query = $request->query->get('q');
        
        if ($query === null || trim($query) === '') {
            return $this->jsonApi->error(400, 'Search query is required')
                ->withCode('query_required')
                ->withSource(parameter: 'q')
                ->build();
        }

        // Perform search
        $results = $this->articleRepository->search($query, limit: 20);
        $total = $this->articleRepository->searchCount($query);

        return $this->jsonApi->collection('articles', $results)
            ->withTotalItems($total)
            ->withMeta([
                'query' => $query,
                'executionTime' => 0.123,
                'cached' => false,
            ])
            ->withInclude(['author', 'tags'])
            ->withSparseFieldsets([
                'articles' => ['title', 'summary', 'publishedAt'],
                'people' => ['name', 'avatar'],
            ])
            ->build();
    }

    #[Route('/api/articles/trending', methods: ['GET'])]
    public function trending(): Response
    {
        $articles = $this->articleRepository->findTrending(limit: 10);

        return $this->jsonApi->collection('articles', $articles)
            ->withMeta([
                'algorithm' => 'v2',
                'period' => '24h',
                'cached' => true,
                'cacheExpiry' => time() + 3600,
            ])
            ->withHeader('Cache-Control', 'public, max-age=3600')
            ->build();
    }
}
```

## Example 4: Batch Operations

Handling batch operations with validation:

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use AlexFigures\Symfony\Http\Response\JsonApiResponseFactory;
use App\Service\ArticleService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BatchController
{
    public function __construct(
        private readonly JsonApiResponseFactory $jsonApi,
        private readonly ArticleService $articleService,
    ) {}

    #[Route('/api/articles/batch-publish', methods: ['POST'])]
    public function batchPublish(Request $request): Response
    {
        $payload = json_decode($request->getContent(), true);
        
        if (!isset($payload['articleIds']) || !is_array($payload['articleIds'])) {
            return $this->jsonApi->error(400, 'articleIds array is required')
                ->withCode('invalid_payload')
                ->withSource(pointer: '/articleIds')
                ->build();
        }

        $articleIds = $payload['articleIds'];
        
        if (count($articleIds) === 0) {
            return $this->jsonApi->error(400, 'At least one article ID is required')
                ->withCode('empty_batch')
                ->build();
        }

        if (count($articleIds) > 100) {
            return $this->jsonApi->error(400, 'Maximum 100 articles per batch')
                ->withCode('batch_too_large')
                ->withMeta(['maxSize' => 100, 'actualSize' => count($articleIds)])
                ->build();
        }

        // Validate all IDs exist
        $errors = [];
        foreach ($articleIds as $index => $id) {
            if (!$this->articleService->exists($id)) {
                $errors[] = [
                    'pointer' => "/articleIds/{$index}",
                    'detail' => "Article with ID '{$id}' not found",
                    'code' => 'article_not_found',
                ];
            }
        }

        if ($errors !== []) {
            return $this->jsonApi->validationErrors($errors)->build();
        }

        // Queue batch operation
        $job = $this->articleService->queueBatchPublish($articleIds);

        return $this->jsonApi->accepted()
            ->withMeta([
                'jobId' => $job->getId(),
                'articleCount' => count($articleIds),
                'status' => 'queued',
                'estimatedDuration' => count($articleIds) * 2, // seconds
            ])
            ->withLinks([
                'status' => "/api/jobs/{$job->getId()}",
            ])
            ->build();
    }
}
```

## Example 5: Export Endpoint

Generating exports with custom status:

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use AlexFigures\Symfony\Http\Response\JsonApiResponseFactory;
use App\Service\ExportService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ExportController
{
    public function __construct(
        private readonly JsonApiResponseFactory $jsonApi,
        private readonly ExportService $exportService,
    ) {}

    #[Route('/api/articles/export', methods: ['POST'])]
    public function export(Request $request): Response
    {
        $payload = json_decode($request->getContent(), true);
        $format = $payload['format'] ?? 'csv';

        $allowedFormats = ['csv', 'json', 'xlsx'];
        if (!in_array($format, $allowedFormats, true)) {
            return $this->jsonApi->error(400, 'Invalid export format')
                ->withCode('invalid_format')
                ->withMeta(['allowedFormats' => $allowedFormats])
                ->build();
        }

        // Create export job
        $export = $this->exportService->createExport($format, $payload['filters'] ?? []);

        return $this->jsonApi->created('exports', $export)
            ->withMeta([
                'format' => $format,
                'status' => 'processing',
                'estimatedCompletion' => time() + 300,
            ])
            ->withLinks([
                'status' => "/api/exports/{$export->getId()}",
                'download' => "/api/exports/{$export->getId()}/download",
            ])
            ->build();
    }

    #[Route('/api/exports/{id}', methods: ['GET'])]
    public function status(string $id): Response
    {
        $export = $this->exportService->find($id);

        if ($export === null) {
            return $this->jsonApi->error(404, 'Export not found')
                ->withCode('export_not_found')
                ->build();
        }

        return $this->jsonApi->resource('exports', $export)
            ->withMeta([
                'progress' => $export->getProgress(),
                'status' => $export->getStatus(),
            ])
            ->build();
    }
}
```

## See Also

- [Response Factory Guide](../guide/response-factory.md) - Complete API reference
- [Custom Handlers](../guide/custom-handlers.md) - Handler-based approach
- [Custom Routes](../guide/custom-routes.md) - Defining custom routes

