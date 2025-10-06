<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Bridge\Symfony\EventSubscriber;

use JsonApi\Symfony\Http\Exception\NotAcceptableException;
use JsonApi\Symfony\Http\Exception\UnsupportedMediaTypeException;
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

        if ($this->mediaType !== $this->normalizeMediaType($contentType)) {
            throw new UnsupportedMediaTypeException($contentType, 'JSON:API requires the "application/vnd.api+json" media type.');
        }
    }

    private function assertAcceptHeader(Request $request): void
    {
        $accept = $request->headers->get('Accept');
        if ($accept === null || $accept === '') {
            return;
        }

        foreach (explode(',', $accept) as $part) {
            if ($this->mediaType === $this->normalizeMediaType($part)) {
                return;
            }
        }

        throw new NotAcceptableException($accept, 'Requested representation is not available in application/vnd.api+json.');
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

    private static function addVaryAccept(Response $response): void
    {
        $response->headers->set('Vary', self::mergeVaryHeader($response, 'Accept'), false);
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
