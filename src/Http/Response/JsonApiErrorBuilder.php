<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Response;

use AlexFigures\Symfony\Http\Error\ErrorObject;
use AlexFigures\Symfony\Http\Error\ErrorSource;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fluent builder for constructing JSON:API error responses.
 *
 * This builder provides a chainable API for customizing JSON:API error responses
 * with codes, titles, sources, meta, and links.
 *
 * Example usage:
 * ```php
 * return $this->jsonApi->error(400, 'File is required')
 *     ->withCode('file_required')
 *     ->withTitle('Missing File')
 *     ->withSource(pointer: '/data/attributes/file')
 *     ->withMeta(['maxSize' => '10MB'])
 *     ->build();
 * ```
 *
 * @api This class is part of the public API and follows semantic versioning.
 * @since 0.4.0
 */
final class JsonApiErrorBuilder
{
    private ?string $code = null;
    private ?string $title = null;
    private ?ErrorSource $source = null;

    /** @var array<string, mixed> */
    private array $meta = [];

    /** @var array<string, string> */
    private array $links = [];

    /** @var array<string, string> */
    private array $headers = [];

    /**
     * @param int                                                                              $status           HTTP status code
     * @param string|null                                                                      $detail           Error detail message
     * @param list<array{pointer: string, detail: string, code?: string, title?: string}>|null $validationErrors Array of validation errors
     */
    public function __construct(
        private readonly JsonApiResponseFactory $factory,
        private readonly int $status,
        private readonly ?string $detail = null,
        private readonly ?array $validationErrors = null,
    ) {
    }

    /**
     * Set the error code.
     *
     * Example:
     * ```php
     * ->withCode('file_required')
     * ```
     *
     * @param string $code Application-specific error code
     *
     * @return self New instance with error code
     */
    public function withCode(string $code): self
    {
        $clone = clone $this;
        $clone->code = $code;
        return $clone;
    }

    /**
     * Set the error title.
     *
     * Example:
     * ```php
     * ->withTitle('Missing File')
     * ```
     *
     * @param string $title Short, human-readable summary of the problem
     *
     * @return self New instance with error title
     */
    public function withTitle(string $title): self
    {
        $clone = clone $this;
        $clone->title = $title;
        return $clone;
    }

    /**
     * Set the error source.
     *
     * Specify which part of the request caused the error.
     *
     * Example:
     * ```php
     * ->withSource(pointer: '/data/attributes/email')
     * ->withSource(parameter: 'filter[status]')
     * ->withSource(header: 'Authorization')
     * ```
     *
     * @param string|null $pointer   JSON Pointer to the associated entity in the request document
     * @param string|null $parameter Query parameter that caused the error
     * @param string|null $header    HTTP header that caused the error
     *
     * @return self New instance with error source
     */
    public function withSource(?string $pointer = null, ?string $parameter = null, ?string $header = null): self
    {
        $clone = clone $this;
        $clone->source = new ErrorSource($pointer, $parameter, $header);
        return $clone;
    }

    /**
     * Add meta information to the error.
     *
     * Example:
     * ```php
     * ->withMeta(['maxSize' => '10MB', 'allowedTypes' => ['image/jpeg', 'image/png']])
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
     * Add links to the error.
     *
     * Example:
     * ```php
     * ->withLinks(['about' => 'https://docs.example.com/errors/file-required'])
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
     * Example:
     * ```php
     * ->withHeader('X-Error-Id', $errorId)
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
     * Build the final JSON:API error response.
     *
     * @return Response Symfony HTTP response with JSON:API error document
     */
    public function build(): Response
    {
        $errors = [];

        if ($this->validationErrors !== null) {
            // Build multiple validation errors
            foreach ($this->validationErrors as $error) {
                $errors[] = $this->buildValidationError($error);
            }
        } else {
            // Build single error
            $errors[] = $this->buildSingleError();
        }

        $document = [
            'jsonapi' => ['version' => '1.1'],
            'errors' => array_map([$this, 'serializeError'], $errors),
        ];

        // Add top-level links if provided
        if ($this->links !== []) {
            $document['links'] = $this->links;
        }

        // Add top-level meta if provided
        if ($this->meta !== [] && $this->validationErrors === null) {
            $document['meta'] = $this->meta;
        }

        $headers = array_merge(
            ['Content-Type' => 'application/vnd.api+json'],
            $this->headers
        );

        return new JsonResponse($document, $this->status, $headers);
    }

    /**
     * Build a single error object.
     */
    private function buildSingleError(): ErrorObject
    {
        return $this->factory->getErrorBuilder()->create(
            status: (string) $this->status,
            code: $this->code ?? 'error',
            title: $this->title,
            detail: $this->detail,
            source: $this->source,
            meta: $this->meta,
        );
    }

    /**
     * Build a validation error object.
     *
     * @param array{pointer: string, detail: string, code?: string, title?: string} $error
     */
    private function buildValidationError(array $error): ErrorObject
    {
        return $this->factory->getErrorBuilder()->fromPointer(
            status: (string) $this->status,
            code: $error['code'] ?? 'validation_error',
            title: $error['title'] ?? 'Validation Failed',
            detail: $error['detail'],
            pointer: $error['pointer'],
        );
    }

    /**
     * Serialize an ErrorObject to array format.
     *
     * @return array<string, mixed>
     */
    private function serializeError(ErrorObject $error): array
    {
        $result = [
            'status' => $error->status,
            'code' => $error->code,
        ];

        if ($error->title !== null) {
            $result['title'] = $error->title;
        }

        if ($error->detail !== null) {
            $result['detail'] = $error->detail;
        }

        if ($error->source !== null) {
            $source = [];
            if ($error->source->pointer !== null) {
                $source['pointer'] = $error->source->pointer;
            }
            if ($error->source->parameter !== null) {
                $source['parameter'] = $error->source->parameter;
            }
            if ($error->source->header !== null) {
                $source['header'] = $error->source->header;
            }
            if ($source !== []) {
                $result['source'] = $source;
            }
        }

        if ($error->meta !== []) {
            $result['meta'] = $error->meta;
        }

        if ($error->aboutLink !== null) {
            $result['links'] = ['about' => $error->aboutLink];
        }

        return $result;
    }
}
