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
        private readonly MediaTypePolicyProviderInterface $policyProvider,
        private readonly bool $atomicEnabled = false,
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

        $candidates = \AlexFigures\Symfony\Http\Negotiation\ParsedMediaType::parse($contentType);
        $media = count($candidates) === 1 ? $candidates[0] : null;
        if ($media === null || !in_array($media->name, $policy->allowedRequestTypes, true)) {
            throw new UnsupportedMediaTypeException($contentType, sprintf('The "%s" media type is not allowed for this endpoint.', $media->name ?? $contentType));
        }
        if ($policy->enforceJsonApiParameters && !$media->validJsonApi($this->supportedExtensions())) {
            $message = array_diff(array_keys($media->parameters), ['ext', 'profile']) !== []
                ? 'JSON:API media type must not have parameters other than "ext" or "profile".'
                : 'JSON:API media type contains unsupported extension URI in "ext" parameter.';
            throw new UnsupportedMediaTypeException($contentType, $message);
        }
    }

    private function assertAcceptHeader(Request $request, MediaTypePolicy $policy): void
    {
        if ($policy->allowsAnyResponseType()) {
            return;
        }
        $accept = $request->headers->get('Accept');
        if ($accept === null || $accept === '') {
            return;
        }
        $failure = sprintf('Requested representation is not available. Allowed types: %s.', implode(', ', $policy->negotiableResponseTypes));
        $candidates = \AlexFigures\Symfony\Http\Negotiation\ParsedMediaType::parse($accept, true);
        foreach ($candidates as $media) {
            if ($media->quality <= 0 || !$this->isAcceptable($media->name, $policy->negotiableResponseTypes)) {
                continue;
            }
            if ($policy->enforceJsonApiParameters && $media->name === 'application/vnd.api+json'
                && !$media->validJsonApi($this->supportedExtensions())) {
                $failure = array_diff(array_keys($media->parameters), ['ext', 'profile']) !== []
                    ? 'JSON:API media type in Accept header must not have parameters other than "ext" or "profile".'
                    : 'JSON:API media type in Accept header contains unsupported extension URI in "ext" parameter.';
                continue;
            }
            if (str_contains($media->name, '*')) {
                $available = false;
                foreach ($policy->negotiableResponseTypes as $name) {
                    if (!$this->isAcceptable($media->name, [$name])) {
                        continue;
                    }
                    $excluded = false;
                    foreach ($candidates as $explicit) {
                        if ($explicit->name === $name && $explicit->quality === 0.0 && $explicit->parameters === []) {
                            $excluded = true;
                        }
                    }
                    $available = $available || !$excluded;
                }
                if (!$available) {
                    continue;
                }
            }
            return;
        }
        throw new NotAcceptableException($accept, $failure);
    }

    /** @return list<string> */
    private function supportedExtensions(): array
    {
        return $this->atomicEnabled ? ['https://jsonapi.org/ext/atomic'] : [];
    }

    /** @param list<string> $allowed */

    private function isAcceptable(string $normalized, array $allowed): bool
    {
        if (in_array($normalized, $allowed, true)) {
            return true;
        }

        if ($normalized === '*/*') {
            return true;
        }

        if (str_contains($normalized, '/*')) {
            $slashPos = strpos($normalized, '/');
            if ($slashPos === false) {
                return false;
            }

            $prefix = substr($normalized, 0, $slashPos);
            foreach ($allowed as $type) {
                if (str_starts_with($type, $prefix . '/')) {
                    return true;
                }
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
