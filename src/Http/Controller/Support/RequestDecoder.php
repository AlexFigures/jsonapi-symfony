<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Http\Controller\Support;

use AlexFigures\JsonApi\Http\Error\ErrorMapper;
use AlexFigures\JsonApi\Http\Exception\BadRequestException;
use AlexFigures\JsonApi\Http\Exception\UnsupportedMediaTypeException;
use AlexFigures\JsonApi\Http\Negotiation\MediaType;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Decodes and validates JSON:API request bodies.
 *
 * This service handles:
 * - Content-Type validation (must be application/vnd.api+json)
 * - JSON parsing and validation
 * - Request body structure validation
 * @internal
 */
final readonly class RequestDecoder
{
    public function __construct(
        private ErrorMapper $errors,
        private ?\AlexFigures\JsonApi\Http\Negotiation\MediaTypePolicyProviderInterface $policyProvider = null,
    ) {
    }

    /**
     * Decode and validate JSON:API request body.
     *
     * @return array<string, mixed>
     *
     * @throws UnsupportedMediaTypeException if Content-Type is not application/vnd.api+json
     * @throws BadRequestException           if request body is empty, malformed, or not a valid JSON object
     */
    public function decode(Request $request): array
    {
        $this->validateContentType($request);
        $content = $this->extractContent($request);

        return $this->parseJson($content);
    }

    /**
     * Validate that Content-Type header is application/vnd.api+json.
     *
     * @throws UnsupportedMediaTypeException
     */
    private function validateContentType(Request $request): void
    {
        $contentType = $request->headers->get('Content-Type');

        if ($contentType === null) {
            return;
        }

        $candidates = \AlexFigures\JsonApi\Http\Negotiation\ParsedMediaType::parse($contentType);
        $normalized = count($candidates) === 1 ? $candidates[0]->name : '';

        $policy = $this->policyProvider?->getPolicy($request);
        $allowed = $policy->allowedRequestTypes ?? [MediaType::JSON_API];
        if ($normalized === '' || ($allowed !== ['*'] && !in_array($normalized, $allowed, true))) {
            throw new UnsupportedMediaTypeException(
                $contentType,
                'The request media type is not allowed by the configured JSON:API endpoint policy.'
            );
        }
    }

    /**
     * Extract request content and validate it's not empty.
     *
     * @throws BadRequestException
     */
    private function extractContent(Request $request): string
    {
        $content = (string) $request->getContent();

        if ($content === '') {
            throw new BadRequestException(
                'Request body must not be empty.',
                [$this->errors->invalidPointer('/', 'Request body must not be empty.')]
            );
        }

        return $content;
    }

    /**
     * Parse and validate JSON content.
     *
     * @return array<string, mixed>
     *
     * @throws BadRequestException
     */
    private function parseJson(string $content): array
    {
        try {
            $decoded = \AlexFigures\JsonApi\Http\Write\JsonDocument::decode($content);
        } catch (\JsonException $exception) {
            throw new BadRequestException('Malformed JSON.', [$this->errors->invalidJson($exception)], previous: $exception);
        }

        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new BadRequestException(
                'Request body must be a valid JSON object.',
                [$this->errors->invalidPointer('/', 'Request body must be a valid JSON object.')]
            );
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

}
