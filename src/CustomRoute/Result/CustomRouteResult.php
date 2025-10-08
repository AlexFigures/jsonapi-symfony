<?php

declare(strict_types=1);

namespace JsonApi\Symfony\CustomRoute\Result;

use Symfony\Component\HttpFoundation\Response;

/**
 * Represents the result of a custom route handler.
 *
 * Provides a fluent API for defining what to return from a custom route handler.
 * The bundle automatically converts this result into a proper JSON:API response
 * with correct document structure, headers, and status codes.
 *
 * Factory methods for success responses:
 * - resource()    - Return a single resource (200 OK or custom status)
 * - collection()  - Return a collection of resources with pagination
 * - created()     - Return a newly created resource (201 Created)
 * - accepted()    - Return 202 Accepted for async operations
 * - noContent()   - Return 204 No Content
 *
 * Factory methods for error responses:
 * - badRequest()     - Return 400 Bad Request
 * - forbidden()      - Return 403 Forbidden
 * - notFound()       - Return 404 Not Found
 * - conflict()       - Return 409 Conflict
 * - unprocessable()  - Return 422 Unprocessable Entity with validation errors
 *
 * Fluent modifiers:
 * - withMeta()    - Add top-level meta to the response
 * - withLinks()   - Add top-level links to the response
 * - withStatus()  - Override the HTTP status code
 * - withHeader()  - Add a custom HTTP header
 *
 * @api This class is part of the public API and follows semantic versioning.
 * @since 0.3.0
 */
final class CustomRouteResult
{
    private const TYPE_RESOURCE = 'resource';
    private const TYPE_COLLECTION = 'collection';
    private const TYPE_NO_CONTENT = 'no_content';
    private const TYPE_ERROR = 'error';

    /**
     * @param string $type Result type (resource, collection, no_content, error)
     * @param mixed $data The data to return (entity, array of entities, error details, or null)
     * @param int $status HTTP status code
     * @param array<string, mixed> $meta Top-level meta object
     * @param array<string, string> $links Top-level links object
     * @param array<string, string> $headers Additional HTTP headers
     * @param int|null $totalItems Total item count for collections (for pagination)
     */
    private function __construct(
        private readonly string $type,
        private readonly mixed $data,
        private readonly int $status,
        private readonly array $meta = [],
        private readonly array $links = [],
        private readonly array $headers = [],
        private readonly ?int $totalItems = null,
    ) {}

    // ========== Factory Methods: Success Responses ==========

    /**
     * Return a single resource.
     *
     * The resource will be automatically serialized to a JSON:API resource object
     * with proper type, id, attributes, and relationships.
     *
     * Example:
     * ```php
     * return CustomRouteResult::resource($article);
     * ```
     *
     * @param object $resource The resource entity to return
     * @param int $status HTTP status code (default: 200 OK)
     *
     * @return self
     */
    public static function resource(object $resource, int $status = Response::HTTP_OK): self
    {
        return new self(self::TYPE_RESOURCE, $resource, $status);
    }

    /**
     * Return a collection of resources.
     *
     * The resources will be automatically serialized to a JSON:API collection
     * with proper pagination links and meta.
     *
     * Example:
     * ```php
     * return CustomRouteResult::collection($articles, 100);
     * ```
     *
     * @param list<object> $resources Array of resource entities
     * @param int|null $totalItems Total number of items (for pagination). If null, uses count($resources)
     * @param int $status HTTP status code (default: 200 OK)
     *
     * @return self
     */
    public static function collection(array $resources, ?int $totalItems = null, int $status = Response::HTTP_OK): self
    {
        return new self(
            self::TYPE_COLLECTION,
            $resources,
            $status,
            totalItems: $totalItems ?? count($resources)
        );
    }

    /**
     * Return 204 No Content.
     *
     * Use this for operations that succeed but don't return any data,
     * such as DELETE operations or bulk updates.
     *
     * Example:
     * ```php
     * return CustomRouteResult::noContent();
     * ```
     *
     * @return self
     */
    public static function noContent(): self
    {
        return new self(self::TYPE_NO_CONTENT, null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Return 202 Accepted (for async operations).
     *
     * Use this when an operation has been accepted for processing but hasn't
     * completed yet. Optionally include a resource representing the job/task.
     *
     * Example:
     * ```php
     * return CustomRouteResult::accepted()
     *     ->withMeta(['jobId' => $job->id, 'status' => 'pending'])
     *     ->withLinks(['status' => "/api/jobs/{$job->id}"]);
     * ```
     *
     * @param object|null $resource Optional resource to return (e.g., a job object)
     *
     * @return self
     */
    public static function accepted(?object $resource = null): self
    {
        return new self(
            $resource ? self::TYPE_RESOURCE : self::TYPE_NO_CONTENT,
            $resource,
            Response::HTTP_ACCEPTED
        );
    }

    /**
     * Return 201 Created.
     *
     * Use this when a new resource has been successfully created.
     * The Location header will be automatically set to the resource's self link.
     *
     * Example:
     * ```php
     * return CustomRouteResult::created($newArticle);
     * ```
     *
     * @param object $resource The newly created resource
     *
     * @return self
     */
    public static function created(object $resource): self
    {
        return new self(self::TYPE_RESOURCE, $resource, Response::HTTP_CREATED);
    }

    // ========== Factory Methods: Error Responses ==========

    /**
     * Return 400 Bad Request.
     *
     * Use this when the request is malformed or contains invalid data.
     *
     * Example:
     * ```php
     * return CustomRouteResult::badRequest('Query parameter "q" is required');
     * ```
     *
     * @param string $detail Human-readable error detail
     *
     * @return self
     */
    public static function badRequest(string $detail): self
    {
        return new self(
            self::TYPE_ERROR,
            ['detail' => $detail, 'status' => '400'],
            Response::HTTP_BAD_REQUEST
        );
    }

    /**
     * Return 403 Forbidden.
     *
     * Use this when the user is authenticated but doesn't have permission
     * to perform the operation.
     *
     * Example:
     * ```php
     * return CustomRouteResult::forbidden('You do not have permission to publish articles');
     * ```
     *
     * @param string $detail Human-readable error detail (default: 'Access forbidden')
     *
     * @return self
     */
    public static function forbidden(string $detail = 'Access forbidden'): self
    {
        return new self(
            self::TYPE_ERROR,
            ['detail' => $detail, 'status' => '403'],
            Response::HTTP_FORBIDDEN
        );
    }

    /**
     * Return 404 Not Found.
     *
     * Use this when a requested resource doesn't exist.
     *
     * Example:
     * ```php
     * return CustomRouteResult::notFound('Article not found');
     * ```
     *
     * @param string $detail Human-readable error detail (default: 'Resource not found')
     *
     * @return self
     */
    public static function notFound(string $detail = 'Resource not found'): self
    {
        return new self(
            self::TYPE_ERROR,
            ['detail' => $detail, 'status' => '404'],
            Response::HTTP_NOT_FOUND
        );
    }

    /**
     * Return 409 Conflict.
     *
     * Use this when the operation conflicts with the current state of the resource.
     *
     * Example:
     * ```php
     * return CustomRouteResult::conflict('Article is already published');
     * ```
     *
     * @param string $detail Human-readable error detail
     *
     * @return self
     */
    public static function conflict(string $detail): self
    {
        return new self(
            self::TYPE_ERROR,
            ['detail' => $detail, 'status' => '409'],
            Response::HTTP_CONFLICT
        );
    }

    /**
     * Return 422 Unprocessable Entity.
     *
     * Use this for validation errors. Each error should have a 'pointer' and 'detail'.
     *
     * Example:
     * ```php
     * return CustomRouteResult::unprocessable([
     *     ['pointer' => '/data/attributes/email', 'detail' => 'Invalid email format'],
     *     ['pointer' => '/data/attributes/age', 'detail' => 'Must be at least 18'],
     * ]);
     * ```
     *
     * @param list<array{pointer: string, detail: string}> $errors Array of validation errors
     *
     * @return self
     */
    public static function unprocessable(array $errors): self
    {
        return new self(self::TYPE_ERROR, $errors, Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    // ========== Fluent Modifiers ==========

    /**
     * Add top-level meta to the response.
     *
     * Meta is merged with any existing meta. Use this to add custom metadata
     * to the JSON:API document.
     *
     * Example:
     * ```php
     * return CustomRouteResult::resource($article)
     *     ->withMeta(['publishedAt' => $now, 'version' => 2]);
     * ```
     *
     * @param array<string, mixed> $meta Meta object to add
     *
     * @return self New instance with merged meta
     */
    public function withMeta(array $meta): self
    {
        return new self(
            $this->type,
            $this->data,
            $this->status,
            array_merge($this->meta, $meta),
            $this->links,
            $this->headers,
            $this->totalItems,
        );
    }

    /**
     * Add top-level links to the response.
     *
     * Links are merged with any existing links. Use this to add custom links
     * to the JSON:API document.
     *
     * Example:
     * ```php
     * return CustomRouteResult::resource($article)
     *     ->withLinks(['related' => '/api/comments', 'author' => '/api/users/123']);
     * ```
     *
     * @param array<string, string> $links Links object to add
     *
     * @return self New instance with merged links
     */
    public function withLinks(array $links): self
    {
        return new self(
            $this->type,
            $this->data,
            $this->status,
            $this->meta,
            array_merge($this->links, $links),
            $this->headers,
            $this->totalItems,
        );
    }

    /**
     * Override the HTTP status code.
     *
     * Use this when you need a custom status code not covered by the factory methods.
     *
     * Example:
     * ```php
     * return CustomRouteResult::resource($article)
     *     ->withStatus(206); // Partial Content
     * ```
     *
     * @param int $status HTTP status code
     *
     * @return self New instance with updated status
     */
    public function withStatus(int $status): self
    {
        return new self(
            $this->type,
            $this->data,
            $status,
            $this->meta,
            $this->links,
            $this->headers,
            $this->totalItems,
        );
    }

    /**
     * Add a custom HTTP header.
     *
     * Headers are merged with any existing headers. Use this to add custom
     * headers to the response.
     *
     * Example:
     * ```php
     * return CustomRouteResult::resource($article)
     *     ->withHeader('X-Custom-Header', 'value')
     *     ->withHeader('X-Rate-Limit-Remaining', '99');
     * ```
     *
     * @param string $name Header name
     * @param string $value Header value
     *
     * @return self New instance with added header
     */
    public function withHeader(string $name, string $value): self
    {
        return new self(
            $this->type,
            $this->data,
            $this->status,
            $this->meta,
            $this->links,
            array_merge($this->headers, [$name => $value]),
            $this->totalItems,
        );
    }

    // ========== Getters ==========

    /**
     * Get the result type.
     *
     * @return string One of: 'resource', 'collection', 'no_content', 'error'
     *
     * @internal Used by CustomRouteResponseBuilder
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the result data.
     *
     * @return mixed The data (entity, array of entities, error details, or null)
     *
     * @internal Used by CustomRouteResponseBuilder
     */
    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * Get the HTTP status code.
     *
     * @return int HTTP status code
     *
     * @internal Used by CustomRouteResponseBuilder
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * Get the top-level meta object.
     *
     * @return array<string, mixed> Meta object
     *
     * @internal Used by CustomRouteResponseBuilder
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    /**
     * Get the top-level links object.
     *
     * @return array<string, string> Links object
     *
     * @internal Used by CustomRouteResponseBuilder
     */
    public function getLinks(): array
    {
        return $this->links;
    }

    /**
     * Get the custom HTTP headers.
     *
     * @return array<string, string> Headers
     *
     * @internal Used by CustomRouteResponseBuilder
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get the total item count for collections.
     *
     * @return int|null Total items (null for non-collection results)
     *
     * @internal Used by CustomRouteResponseBuilder
     */
    public function getTotalItems(): ?int
    {
        return $this->totalItems;
    }

    // ========== Type Checks ==========

    /**
     * Check if this is an error result.
     *
     * @return bool True if this is an error result
     */
    public function isError(): bool
    {
        return $this->type === self::TYPE_ERROR;
    }

    /**
     * Check if this is a resource result.
     *
     * @return bool True if this is a single resource result
     */
    public function isResource(): bool
    {
        return $this->type === self::TYPE_RESOURCE;
    }

    /**
     * Check if this is a collection result.
     *
     * @return bool True if this is a collection result
     */
    public function isCollection(): bool
    {
        return $this->type === self::TYPE_COLLECTION;
    }

    /**
     * Check if this is a no-content result.
     *
     * @return bool True if this is a no-content result
     */
    public function isNoContent(): bool
    {
        return $this->type === self::TYPE_NO_CONTENT;
    }
}


