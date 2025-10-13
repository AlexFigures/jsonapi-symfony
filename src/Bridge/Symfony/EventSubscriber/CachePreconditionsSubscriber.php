<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Bridge\Symfony\EventSubscriber;

use AlexFigures\Symfony\Http\Cache\CacheKeyBuilder;
use AlexFigures\Symfony\Http\Cache\ConditionalRequestEvaluator;
use AlexFigures\Symfony\Http\Cache\EtagGeneratorInterface;
use AlexFigures\Symfony\Http\Cache\HeadersApplier;
use AlexFigures\Symfony\Http\Cache\LastModifiedResolver;
use AlexFigures\Symfony\Http\Cache\SurrogateKeyBuilder;
use DateTimeImmutable;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @phpstan-type CachePreconditionsConfig array{
 *     enabled?: bool,
 *     etag?: array{weak_for_collections?: bool}
 * }
 */
final class CachePreconditionsSubscriber implements EventSubscriberInterface
{
    /**
     * @param CachePreconditionsConfig $config
     */
    public function __construct(
        array $config,
        private readonly CacheKeyBuilder $cacheKeyBuilder,
        private readonly EtagGeneratorInterface $etagGenerator,
        private readonly LastModifiedResolver $lastModified,
        private readonly ConditionalRequestEvaluator $conditional,
        private readonly HeadersApplier $headers,
        private readonly SurrogateKeyBuilder $surrogates,
    ) {
        $this->enabled = $config['enabled'] ?? true;
        /** @var array{weak_for_collections?: bool} $etagConfig */
        $etagConfig = $config['etag'] ?? [];
        $this->weakForCollections = (bool) ($etagConfig['weak_for_collections'] ?? true);
    }

    private bool $enabled;

    private bool $weakForCollections;

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -1024],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$this->enabled || !$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        if ($this->requiresPreconditions($request)) {
            // For write operations, we need to generate ETag from the current resource state
            // to validate If-Match and If-Unmodified-Since headers
            if ($this->isJsonApiResponse($response)) {
                $cacheKey = $this->cacheKeyBuilder->build($request);
                $weak = false; // Always use strong ETag for write operations
                $etag = $this->etagGenerator->generate($request, $response, $cacheKey, $weak);
                $lastModified = $this->resolveLastModified($request, $response);

                $this->conditional->evaluate($request, $response, $etag, $lastModified, $weak);
            } else {
                // If response is not JSON:API (e.g., error response), evaluate without ETag
                $this->conditional->evaluate($request, $response, null, null);
            }

            return;
        }

        if (!$this->isCacheableMethod($request)) {
            return;
        }

        if (!$this->isJsonApiResponse($response)) {
            return;
        }

        $cacheKey = $this->cacheKeyBuilder->build($request);
        $weak = $this->isCollectionRoute($request) && $this->weakForCollections;
        $etag = $this->etagGenerator->generate($request, $response, $cacheKey, $weak);
        $lastModified = $this->resolveLastModified($request, $response);

        $this->conditional->evaluate($request, $response, $etag, $lastModified, $weak);

        $surrogateKeys = $this->surrogates->build($request);
        $this->headers->apply($response, $etag, $lastModified, $surrogateKeys, $weak);
    }

    private function requiresPreconditions(Request $request): bool
    {
        $method = strtoupper($request->getMethod());

        return in_array($method, ['PATCH', 'PUT', 'DELETE'], true);
    }

    private function isCacheableMethod(Request $request): bool
    {
        $method = strtoupper($request->getMethod());

        return in_array($method, ['GET', 'HEAD'], true);
    }

    private function isJsonApiResponse(Response $response): bool
    {
        $contentType = $response->headers->get('Content-Type');
        if ($contentType === null) {
            return false;
        }

        return str_contains(strtolower($contentType), 'application/vnd.api+json');
    }

    private function isCollectionRoute(Request $request): bool
    {
        return $request->attributes->get('_route') === 'jsonapi.collection';
    }

    private function resolveLastModified(Request $request, Response $response): DateTimeImmutable
    {
        return $this->lastModified->resolve($request, $response);
    }
}
