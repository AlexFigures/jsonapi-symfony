<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Controller\Support;

use AlexFigures\Symfony\Http\Error\ErrorMapper;
use AlexFigures\Symfony\Http\Exception\BadRequestException;
use AlexFigures\Symfony\Http\Exception\UnsupportedMediaTypeException;
use AlexFigures\Symfony\Http\Negotiation\MediaType;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Decodes and validates JSON:API request bodies.
 *
 * This service handles:
 * - Content-Type validation (must be application/vnd.api+json)
 * - JSON parsing and validation
 * - Request body structure validation
 */
final class RequestDecoder
{
    public function __construct(
        private readonly ErrorMapper $errors,
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

        $normalized = $this->normalizeMediaType($contentType);

        if (MediaType::JSON_API !== $normalized) {
            throw new UnsupportedMediaTypeException(
                $contentType,
                'JSON:API requires the "application/vnd.api+json" media type.'
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
        $decoded = json_decode($content, true);

        if ($decoded === null && json_last_error() !== \JSON_ERROR_NONE) {
            $error = $this->errors->invalidJson(
                new RuntimeException(sprintf('Malformed JSON: %s.', json_last_error_msg()))
            );
            throw new BadRequestException('Malformed JSON.', [$error]);
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

    /**
     * Normalize media type by removing parameters.
     */
    private function normalizeMediaType(string $value): string
    {
        $normalized = trim(strtolower($value));
        $semicolonPosition = strpos($normalized, ';');

        return $semicolonPosition === false
            ? $normalized
            : substr($normalized, 0, $semicolonPosition);
    }
}
