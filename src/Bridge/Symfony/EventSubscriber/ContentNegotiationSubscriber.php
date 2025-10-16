<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Bridge\Symfony\EventSubscriber;

use AlexFigures\Symfony\Http\Exception\NotAcceptableException;
use AlexFigures\Symfony\Http\Exception\UnsupportedMediaTypeException;
use AlexFigures\Symfony\Http\Negotiation\MediaTypePolicy;
use AlexFigures\Symfony\Http\Negotiation\MediaTypePolicyProviderInterface;
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
        private readonly MediaTypePolicyProviderInterface $policyProvider
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

        $policy = $this->policyProvider->getPolicy($request);

        $this->assertContentType($request, $policy);
        $this->assertAcceptHeader($request, $policy);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $request = $event->getRequest();
        $policy = $this->policyProvider->getPolicy($request);

        if (!$response->headers->has('Content-Type')) {
            $response->headers->set('Content-Type', $policy->defaultResponseType);
        }

        self::addVaryAccept($response);
    }

    private function assertContentType(Request $request, MediaTypePolicy $policy): void
    {
        if (!$this->strictContentNegotiation || $policy->allowsAnyRequestType()) {
            return;
        }

        $contentType = $request->headers->get('Content-Type');
        if ($contentType === null) {
            return;
        }

        $normalized = $this->normalizeMediaType($contentType);

        if (!in_array($normalized, $policy->allowedRequestTypes, true)) {
            throw new UnsupportedMediaTypeException(
                $contentType,
                sprintf('The "%s" media type is not allowed for this endpoint.', $normalized)
            );
        }

        if ($policy->enforceJsonApiParameters && $this->hasUnsupportedParameters($contentType)) {
            throw new UnsupportedMediaTypeException(
                $contentType,
                'JSON:API media type must not have parameters other than "ext" or "profile".'
            );
        }
    }

    private function assertAcceptHeader(Request $request, MediaTypePolicy $policy): void
    {
        if (!$this->strictContentNegotiation || $policy->allowsAnyResponseType()) {
            return;
        }

        $accept = $request->headers->get('Accept');
        if ($accept === null || $accept === '') {
            return;
        }

        $found = false;
        foreach (explode(',', $accept) as $part) {
            $normalized = $this->normalizeMediaType($part);

            if ($this->isAcceptable($normalized, $policy->negotiableResponseTypes)) {
                $found = true;

                if ($policy->enforceJsonApiParameters && $this->hasUnsupportedParameters($part)) {
                    throw new NotAcceptableException(
                        $accept,
                        'JSON:API media type in Accept header must not have parameters other than "ext" or "profile".'
                    );
                }
            }
        }

        if (!$found) {
            throw new NotAcceptableException(
                $accept,
                sprintf('Requested representation is not available. Allowed types: %s.', implode(', ', $policy->negotiableResponseTypes))
            );
        }
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
     * @param list<string> $allowed
     */
    private function isAcceptable(string $normalized, array $allowed): bool
    {
        if (in_array($normalized, $allowed, true)) {
            return true;
        }

        if ($normalized === '*/*') {
            return true;
        }

        if (str_contains($normalized, '/*')) {
            $prefix = substr($normalized, 0, strpos($normalized, '/'));
            foreach ($allowed as $type) {
                if (str_starts_with($type, $prefix . '/')) {
                    return true;
                }
            }
        }

        return false;
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
