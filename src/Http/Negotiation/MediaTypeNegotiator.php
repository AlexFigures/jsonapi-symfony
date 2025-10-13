<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Negotiation;

use AlexFigures\Symfony\Atomic\AtomicConfig;
use AlexFigures\Symfony\Http\Exception\NotAcceptableException;
use AlexFigures\Symfony\Http\Exception\UnsupportedMediaTypeException;
use Symfony\Component\HttpFoundation\Request;

final class MediaTypeNegotiator
{
    public function __construct(private readonly AtomicConfig $config)
    {
    }

    public function assertAtomicExt(Request $request): void
    {
        if (!$this->config->requireExtHeader) {
            return;
        }

        $contentType = $request->headers->get('Content-Type');
        if ($contentType === null || !$this->containsAtomicExt($contentType)) {
            throw new UnsupportedMediaTypeException($contentType, 'Atomic operations require the JSON:API media type with the atomic extension.');
        }

        $accept = $request->headers->get('Accept');
        if ($accept === null) {
            return;
        }

        if (!$this->acceptsAtomic($accept)) {
            throw new NotAcceptableException($accept, 'The requested media type does not include the JSON:API atomic extension.');
        }
    }

    private function containsAtomicExt(string $mediaType): bool
    {
        return str_contains(strtolower($mediaType), 'ext="https://jsonapi.org/ext/atomic"');
    }

    private function acceptsAtomic(string $accept): bool
    {
        $parts = array_map('trim', explode(',', $accept));

        foreach ($parts as $part) {
            if ($part === '*/*' || $part === MediaType::JSON_API_ATOMIC) {
                return true;
            }

            if (stripos($part, 'application/vnd.api+json') === false) {
                continue;
            }

            if ($this->containsAtomicExt($part)) {
                return true;
            }
        }

        return false;
    }
}
