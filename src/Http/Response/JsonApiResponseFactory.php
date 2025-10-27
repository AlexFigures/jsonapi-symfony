<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Response;

use AlexFigures\Symfony\Http\Document\DocumentBuilder;
use AlexFigures\Symfony\Http\Error\ErrorBuilder;
use AlexFigures\Symfony\Http\Link\LinkGenerator;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
use LogicException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Factory for building JSON:API responses in custom controllers.
 *
 * This factory provides a fluent API for constructing JSON:API compliant responses
 * without manually formatting the document structure. It's designed for use cases
 * that don't fit the standard CRUD or handler-based patterns, such as:
 * - File uploads (multipart/form-data)
 * - Webhooks
 * - Custom integrations
 * - Non-standard endpoints
 *
 * Example usage:
 * ```php
 * class MediaUploadController
 * {
 *     public function __construct(
 *         private JsonApiResponseFactory $jsonApi,
 *     ) {}
 *
 *     #[Route('/api/media/upload', methods: ['POST'])]
 *     public function upload(Request $request): Response
 *     {
 *         $file = $request->files->get('file');
 *         $media = $this->mediaService->createFromUpload($file);
 *
 *         return $this->jsonApi->created('media', $media)
 *             ->withMeta(['uploadedAt' => time()])
 *             ->withLinks(['thumbnail' => '/media/123/thumbnail'])
 *             ->build();
 *     }
 * }
 * ```
 *
 * @api This class is part of the public API and follows semantic versioning.
 * @since 0.4.0
 */
final class JsonApiResponseFactory
{
    public function __construct(
        private readonly DocumentBuilder $documentBuilder,
        private readonly LinkGenerator $linkGenerator,
        private readonly ErrorBuilder $errorBuilder,
        private readonly ResourceRegistryInterface $registry,
    ) {
    }

    /**
     * Create a response for a single resource (200 OK).
     *
     * Example:
     * ```php
     * return $this->jsonApi->resource('articles', $article)
     *     ->withMeta(['cached' => true])
     *     ->build();
     * ```
     *
     * @param string $type     JSON:API resource type
     * @param object $resource The resource entity
     *
     * @return JsonApiResponseBuilder Fluent builder for customizing the response
     */
    public function resource(string $type, object $resource): JsonApiResponseBuilder
    {
        return new JsonApiResponseBuilder(
            factory: $this,
            type: $type,
            mode: 'resource',
            data: $resource,
            status: Response::HTTP_OK,
        );
    }

    /**
     * Create a response for a newly created resource (201 Created).
     *
     * Automatically sets the Location header to the resource's self link.
     *
     * Example:
     * ```php
     * return $this->jsonApi->created('media', $media)
     *     ->withMeta(['uploadedAt' => time()])
     *     ->build();
     * ```
     *
     * @param string $type     JSON:API resource type
     * @param object $resource The newly created resource
     *
     * @return JsonApiResponseBuilder Fluent builder for customizing the response
     */
    public function created(string $type, object $resource): JsonApiResponseBuilder
    {
        return new JsonApiResponseBuilder(
            factory: $this,
            type: $type,
            mode: 'resource',
            data: $resource,
            status: Response::HTTP_CREATED,
            autoLocationHeader: true,
        );
    }

    /**
     * Create a response for a collection of resources (200 OK).
     *
     * Example:
     * ```php
     * return $this->jsonApi->collection('articles', $articles)
     *     ->withTotalItems(100)
     *     ->withMeta(['cached' => true])
     *     ->build();
     * ```
     *
     * @param string        $type      JSON:API resource type
     * @param list<object>  $resources Array of resource entities
     * @param int|null      $totalItems Total number of items (for pagination). If null, uses count($resources)
     *
     * @return JsonApiResponseBuilder Fluent builder for customizing the response
     */
    public function collection(string $type, array $resources, ?int $totalItems = null): JsonApiResponseBuilder
    {
        return new JsonApiResponseBuilder(
            factory: $this,
            type: $type,
            mode: 'collection',
            data: $resources,
            status: Response::HTTP_OK,
            totalItems: $totalItems ?? count($resources),
        );
    }

    /**
     * Create a 204 No Content response.
     *
     * Use this for operations that succeed but don't return any data,
     * such as DELETE operations or bulk updates.
     *
     * Example:
     * ```php
     * return $this->jsonApi->noContent();
     * ```
     *
     * @return Response Symfony Response with 204 status and empty body
     */
    public function noContent(): Response
    {
        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * Create a 202 Accepted response (for async operations).
     *
     * Use this when an operation has been accepted for processing but hasn't
     * completed yet. Optionally include a resource representing the job/task.
     *
     * Example:
     * ```php
     * return $this->jsonApi->accepted()
     *     ->withMeta(['jobId' => $job->id, 'status' => 'pending'])
     *     ->withLinks(['status' => "/api/jobs/{$job->id}"])
     *     ->build();
     * ```
     *
     * @param string|null $type     JSON:API resource type (required if resource is provided)
     * @param object|null $resource Optional resource to return (e.g., a job object)
     *
     * @return JsonApiResponseBuilder Fluent builder for customizing the response
     */
    public function accepted(?string $type = null, ?object $resource = null): JsonApiResponseBuilder
    {
        if ($resource !== null && $type === null) {
            throw new LogicException('Resource type must be provided when resource is not null');
        }

        return new JsonApiResponseBuilder(
            factory: $this,
            type: $type ?? '',
            mode: $resource !== null ? 'resource' : 'empty',
            data: $resource,
            status: Response::HTTP_ACCEPTED,
        );
    }

    /**
     * Create an error response.
     *
     * Example:
     * ```php
     * return $this->jsonApi->error(400, 'File is required')
     *     ->withCode('file_required')
     *     ->build();
     * ```
     *
     * @param int    $status HTTP status code
     * @param string $detail Human-readable error detail
     *
     * @return JsonApiErrorBuilder Fluent builder for customizing the error
     */
    public function error(int $status, string $detail): JsonApiErrorBuilder
    {
        return new JsonApiErrorBuilder(
            factory: $this,
            status: $status,
            detail: $detail,
        );
    }

    /**
     * Create a validation error response (422 Unprocessable Entity).
     *
     * Example:
     * ```php
     * return $this->jsonApi->validationErrors([
     *     ['pointer' => '/data/attributes/email', 'detail' => 'Invalid email format'],
     *     ['pointer' => '/data/attributes/age', 'detail' => 'Must be at least 18'],
     * ])->build();
     * ```
     *
     * @param list<array{pointer: string, detail: string, code?: string, title?: string}> $errors Array of validation errors
     *
     * @return JsonApiErrorBuilder Fluent builder for customizing the errors
     */
    public function validationErrors(array $errors): JsonApiErrorBuilder
    {
        return new JsonApiErrorBuilder(
            factory: $this,
            status: Response::HTTP_UNPROCESSABLE_ENTITY,
            validationErrors: $errors,
        );
    }

    /**
     * @internal Used by JsonApiResponseBuilder
     */
    public function getDocumentBuilder(): DocumentBuilder
    {
        return $this->documentBuilder;
    }

    /**
     * @internal Used by JsonApiResponseBuilder
     */
    public function getLinkGenerator(): LinkGenerator
    {
        return $this->linkGenerator;
    }

    /**
     * @internal Used by JsonApiErrorBuilder
     */
    public function getErrorBuilder(): ErrorBuilder
    {
        return $this->errorBuilder;
    }

    /**
     * @internal Used by JsonApiResponseBuilder
     */
    public function getRegistry(): ResourceRegistryInterface
    {
        return $this->registry;
    }
}

