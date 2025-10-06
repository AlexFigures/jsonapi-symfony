<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Bridge\Symfony\EventSubscriber;

use JsonApi\Symfony\Http\Negotiation\MediaType;
use JsonApi\Symfony\Profile\Negotiation\ProfileNegotiator;
use JsonApi\Symfony\Profile\ProfileContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ProfileNegotiationSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly ProfileNegotiator $negotiator)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 0],
            KernelEvents::RESPONSE => ['onKernelResponse', -64],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $context = $this->negotiator->negotiate($request);
        ProfileContext::store($request, $context);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $context = ProfileContext::fromRequest($request);
        if ($context === null) {
            return;
        }

        $response = $event->getResponse();

        if ($this->negotiator->shouldEmitLinkHeader()) {
            foreach ($context->activeUris() as $uri) {
                $response->headers->set('Link', sprintf('<%s>; rel="profile"', $uri), false);
            }
        }

        if (!$this->negotiator->shouldEchoProfilesInContentType()) {
            return;
        }

        $contentType = $response->headers->get('Content-Type');
        if ($contentType === null || !str_contains($contentType, MediaType::JSON_API)) {
            return;
        }

        $profiles = $context->activeUris();
        if ($profiles === []) {
            return;
        }

        $response->headers->set('Content-Type', $this->withProfileParameter($contentType, $profiles));
    }

    /**
     * @param list<string> $profiles
     */
    private function withProfileParameter(string $contentType, array $profiles): string
    {
        $parts = array_map('trim', explode(';', $contentType));
        $type = array_shift($parts);
        if ($type === '') {
            $type = $contentType;
        }
        $parameters = [];

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if (str_starts_with($part, 'profile=')) {
                continue;
            }

            $parameters[] = $part;
        }

        $parameters[] = sprintf('profile="%s"', implode(' ', $profiles));

        return $type . '; ' . implode('; ', $parameters);
    }
}
