<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Bridge\Symfony\EventSubscriber;

use AlexFigures\Symfony\Http\Exception\NotAcceptableException;
use AlexFigures\Symfony\Http\Exception\UnsupportedMediaTypeException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ContentNegotiationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly bool $strictContentNegotiation,
        private readonly string $mediaType
    ) {
    }

    /**
     * @return array<string, array{0: string, 1?: int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 512],
            KernelEvents::RESPONSE => ['onKernelResponse', -512],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!$this->strictContentNegotiation) {
            return;
        }

        $request = $event->getRequest();

        // Skip content negotiation for documentation routes
        if ($this->isDocumentationRoute($request)) {
            return;
        }

        $this->assertContentType($request);
        $this->assertAcceptHeader($request);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        self::addVaryAccept($response);
    }

    private function assertContentType(Request $request): void
    {
        $contentType = $request->headers->get('Content-Type');
        if ($contentType === null) {
            return;
        }

        $normalized = $this->normalizeMediaType($contentType);

        if ($this->mediaType !== $normalized) {
            throw new UnsupportedMediaTypeException($contentType, 'JSON:API requires the "application/vnd.api+json" media type.');
        }

        // JSON:API spec: servers MUST respond with 415 if media type parameters other than ext or profile are present
        if ($this->hasUnsupportedParameters($contentType)) {
            throw new UnsupportedMediaTypeException(
                $contentType,
                'JSON:API media type must not have parameters other than "ext" or "profile".'
            );
        }
    }

    private function assertAcceptHeader(Request $request): void
    {
        $accept = $request->headers->get('Accept');
        if ($accept === null || $accept === '') {
            return;
        }

        $foundJsonApi = false;
        foreach (explode(',', $accept) as $part) {
            $normalized = $this->normalizeMediaType($part);

            if ($this->mediaType === $normalized) {
                $foundJsonApi = true;

                // JSON:API spec: servers MUST respond with 406 if media type parameters other than ext or profile are present
                if ($this->hasUnsupportedParameters($part)) {
                    throw new NotAcceptableException(
                        $accept,
                        'JSON:API media type in Accept header must not have parameters other than "ext" or "profile".'
                    );
                }
            }
        }

        if (!$foundJsonApi) {
            throw new NotAcceptableException($accept, 'Requested representation is not available in application/vnd.api+json.');
        }
    }

    /**
     * Check if the request is for documentation routes that should not be subject to JSON:API content negotiation.
     */
    private function isDocumentationRoute(Request $request): bool
    {
        $route = $request->attributes->get('_route');

        // Check by route name
        if ($route !== null && str_starts_with((string) $route, 'jsonapi.docs.')) {
            return true;
        }

        // Fallback: check by path pattern
        $path = $request->getPathInfo();
        return str_starts_with($path, '/_jsonapi/docs') || str_starts_with($path, '/_jsonapi/openapi');
    }

    private function normalizeMediaType(string $value): string
    {
        $normalized = trim(strtolower($value));
        $semicolonPosition = strpos($normalized, ';');

        if ($semicolonPosition === false) {
            return $normalized;
        }

        return substr($normalized, 0, $semicolonPosition);
    }

    /**
     * Check if media type has parameters other than 'ext' or 'profile'.
     * According to JSON:API spec, only 'ext' and 'profile' parameters are allowed.
     */
    private function hasUnsupportedParameters(string $mediaType): bool
    {
        $semicolonPosition = strpos($mediaType, ';');

        if ($semicolonPosition === false) {
            return false;
        }

        // Extract parameters part
        $parametersString = substr($mediaType, $semicolonPosition + 1);

        // Parse parameters
        $parts = array_map('trim', explode(';', $parametersString));

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            // Extract parameter name (before '=')
            $equalPosition = strpos($part, '=');
            if ($equalPosition === false) {
                // Parameter without value is unsupported
                return true;
            }

            $paramName = trim(substr($part, 0, $equalPosition));

            // Only 'ext' and 'profile' are allowed
            if ($paramName !== 'ext' && $paramName !== 'profile') {
                return true;
            }
        }

        return false;
    }

    private static function addVaryAccept(Response $response): void
    {
        $response->headers->set('Vary', self::mergeVaryHeader($response, 'Accept'));
    }

    private static function mergeVaryHeader(Response $response, string $value): string
    {
        $existing = $response->headers->get('Vary');

        if ($existing === null || $existing === '') {
            return $value;
        }

        $values = array_map('trim', explode(',', $existing));
        if (!in_array($value, $values, true)) {
            $values[] = $value;
        }

        return implode(', ', $values);
    }
}
