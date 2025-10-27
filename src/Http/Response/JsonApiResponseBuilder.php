<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Response;

use AlexFigures\Symfony\Contract\Data\Slice;
use AlexFigures\Symfony\Query\Criteria;
use AlexFigures\Symfony\Query\Pagination;
use LogicException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fluent builder for constructing JSON:API responses.
 *
 * This builder provides a chainable API for customizing JSON:API responses
 * with meta, links, includes, sparse fieldsets, and custom headers.
 *
 * Example usage:
 * ```php
 * return $this->jsonApi->created('media', $media)
 *     ->withMeta(['uploadedAt' => time(), 'size' => $media->size])
 *     ->withLinks(['thumbnail' => '/media/123/thumbnail'])
 *     ->withInclude(['author', 'tags'])
 *     ->withHeader('X-Upload-Id', $uploadId)
 *     ->build();
 * ```
 *
 * @api This class is part of the public API and follows semantic versioning.
 * @since 0.4.0
 */
final class JsonApiResponseBuilder
{
    /** @var array<string, mixed> */
    private array $meta = [];

    /** @var array<string, string> */
    private array $links = [];

    /** @var array<string, string> */
    private array $headers = [];

    /** @var list<string> */
    private array $include = [];

    /** @var array<string, list<string>> */
    private array $sparseFieldsets = [];

    private ?Request $request = null;

    /**
     * @param string                   $type               JSON:API resource type
     * @param string                   $mode               Response mode: 'resource', 'collection', 'empty'
     * @param object|list<object>|null $data               Resource(s) or null for empty responses
     * @param int                      $status             HTTP status code
     * @param int|null                 $totalItems         Total items for collections (pagination)
     * @param bool                     $autoLocationHeader Whether to automatically add Location header (for 201 Created)
     */
    public function __construct(
        private readonly JsonApiResponseFactory $factory,
        private readonly string $type,
        private readonly string $mode,
        private readonly object|array|null $data,
        private int $status,
        private ?int $totalItems = null,
        private readonly bool $autoLocationHeader = false,
    ) {
    }

    /**
     * Add top-level meta to the response.
     *
     * Meta is merged with any existing meta. Use this to add custom metadata
     * to the JSON:API document.
     *
     * Example:
     * ```php
     * ->withMeta(['cached' => true, 'version' => 2])
     * ```
     *
     * @param array<string, mixed> $meta Meta object to add
     *
     * @return self New instance with merged meta
     */
    public function withMeta(array $meta): self
    {
        $clone = clone $this;
        $clone->meta = array_merge($this->meta, $meta);
        return $clone;
    }

    /**
     * Add top-level links to the response.
     *
     * Links are merged with any existing links. Use this to add custom links
     * to the JSON:API document.
     *
     * Example:
     * ```php
     * ->withLinks(['related' => '/api/comments', 'author' => '/api/users/123'])
     * ```
     *
     * @param array<string, string> $links Links object to add
     *
     * @return self New instance with merged links
     */
    public function withLinks(array $links): self
    {
        $clone = clone $this;
        $clone->links = array_merge($this->links, $links);
        return $clone;
    }

    /**
     * Add a custom HTTP header.
     *
     * Headers are merged with any existing headers. Use this to add custom
     * headers to the response.
     *
     * Example:
     * ```php
     * ->withHeader('X-Upload-Id', $uploadId)
     * ->withHeader('X-Rate-Limit-Remaining', '99')
     * ```
     *
     * @param string $name  Header name
     * @param string $value Header value
     *
     * @return self New instance with added header
     */
    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    /**
     * Override the HTTP status code.
     *
     * Use this when you need a custom status code not covered by the factory methods.
     *
     * Example:
     * ```php
     * ->withStatus(206) // Partial Content
     * ```
     *
     * @param int $status HTTP status code
     *
     * @return self New instance with updated status
     */
    public function withStatus(int $status): self
    {
        $clone = clone $this;
        $clone->status = $status;
        return $clone;
    }

    /**
     * Specify relationships to include in the response.
     *
     * This enables compound documents with included resources.
     *
     * Example:
     * ```php
     * ->withInclude(['author', 'comments', 'comments.author'])
     * ```
     *
     * @param list<string> $include Array of relationship paths to include
     *
     * @return self New instance with includes
     */
    public function withInclude(array $include): self
    {
        $clone = clone $this;
        $clone->include = $include;
        return $clone;
    }

    /**
     * Specify sparse fieldsets for the response.
     *
     * This limits which attributes are returned for each resource type.
     *
     * Example:
     * ```php
     * ->withSparseFieldsets([
     *     'articles' => ['title', 'body'],
     *     'people' => ['name']
     * ])
     * ```
     *
     * @param array<string, list<string>> $fieldsets Map of resource type => field names
     *
     * @return self New instance with sparse fieldsets
     */
    public function withSparseFieldsets(array $fieldsets): self
    {
        $clone = clone $this;
        $clone->sparseFieldsets = $fieldsets;
        return $clone;
    }

    /**
     * Set total items count for collections (for pagination).
     *
     * If not set, defaults to count($resources).
     *
     * Example:
     * ```php
     * ->withTotalItems(1000) // Total items in database
     * ```
     *
     * @param int $totalItems Total number of items
     *
     * @return self New instance with total items
     */
    public function withTotalItems(int $totalItems): self
    {
        $clone = clone $this;
        $clone->totalItems = $totalItems;
        return $clone;
    }

    /**
     * Provide the current request for context.
     *
     * This allows the builder to extract query parameters for pagination,
     * includes, sparse fieldsets, etc. If not provided, a minimal request
     * will be created.
     *
     * Example:
     * ```php
     * ->withRequest($request)
     * ```
     *
     * @param Request $request The current HTTP request
     *
     * @return self New instance with request
     */
    public function withRequest(Request $request): self
    {
        $clone = clone $this;
        $clone->request = $request;
        return $clone;
    }

    /**
     * Build the final JSON:API response.
     *
     * @return Response Symfony HTTP response with JSON:API document
     */
    public function build(): Response
    {
        if ($this->mode === 'empty') {
            return $this->buildEmptyResponse();
        }

        if ($this->mode === 'resource') {
            return $this->buildResourceResponse();
        }

        if ($this->mode === 'collection') {
            return $this->buildCollectionResponse();
        }

        throw new LogicException("Invalid mode: {$this->mode}");
    }

    /**
     * Build an empty response (e.g., for 202 Accepted without resource).
     */
    private function buildEmptyResponse(): JsonResponse
    {
        $document = ['jsonapi' => ['version' => '1.1']];

        if ($this->meta !== []) {
            $document['meta'] = $this->meta;
        }

        if ($this->links !== []) {
            $document['links'] = $this->links;
        }

        return $this->createJsonResponse($document);
    }

    /**
     * Build a single resource response.
     */
    private function buildResourceResponse(): JsonResponse
    {
        if (!is_object($this->data)) {
            throw new LogicException('Resource mode requires an object as data');
        }

        $criteria = $this->buildCriteria();
        $request = $this->getOrCreateRequest();

        $document = $this->factory->getDocumentBuilder()->buildResource(
            $this->type,
            $this->data,
            $criteria,
            $request
        );

        // Merge custom meta
        if ($this->meta !== []) {
            $document['meta'] = isset($document['meta'])
                ? array_merge($document['meta'], $this->meta)
                : $this->meta;
        }

        // Merge custom links
        if ($this->links !== []) {
            $document['links'] = array_merge($document['links'], $this->links);
        }

        // Add Location header for 201 Created
        if ($this->autoLocationHeader && $this->status === Response::HTTP_CREATED) {
            $resourceId = $document['data']['id'];
            $this->headers['Location'] = $this->factory->getLinkGenerator()->resourceSelf(
                $this->type,
                (string) $resourceId
            );
        }

        return $this->createJsonResponse($document);
    }

    /**
     * Build a collection response.
     */
    private function buildCollectionResponse(): JsonResponse
    {
        if (!is_array($this->data)) {
            throw new LogicException('Collection mode requires an array as data');
        }

        /** @var list<object> $resources */
        $resources = $this->data;

        $criteria = $this->buildCriteria();
        $request = $this->getOrCreateRequest();

        $totalItems = $this->totalItems ?? count($resources);

        $slice = new Slice(
            items: $resources,
            totalItems: $totalItems,
            pageNumber: $criteria->pagination->number,
            pageSize: $criteria->pagination->size,
        );

        $document = $this->factory->getDocumentBuilder()->buildCollection(
            $this->type,
            $resources,
            $criteria,
            $slice,
            $request
        );

        // Merge custom meta
        if ($this->meta !== []) {
            $document['meta'] = array_merge($document['meta'], $this->meta);
        }

        // Merge custom links
        if ($this->links !== []) {
            $document['links'] = array_merge($document['links'], $this->links);
        }

        return $this->createJsonResponse($document);
    }

    private function buildCriteria(): Criteria
    {
        $criteria = new Criteria();
        $criteria->include = $this->include;
        $criteria->fields = $this->sparseFieldsets;

        return $criteria;
    }

    private function getOrCreateRequest(): Request
    {
        if ($this->request !== null) {
            return $this->request;
        }

        // Create a minimal request for DocumentBuilder
        return Request::create('https://api.example.com/');
    }

    /**
     * @param array<string, mixed> $document
     */
    private function createJsonResponse(array $document): JsonResponse
    {
        $headers = array_merge(
            ['Content-Type' => 'application/vnd.api+json'],
            $this->headers
        );

        return new JsonResponse($document, $this->status, $headers);
    }
}
