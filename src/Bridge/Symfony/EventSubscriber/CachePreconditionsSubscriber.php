<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Bridge\Symfony\EventSubscriber;

use AlexFigures\JsonApi\Http\Cache\CacheKeyBuilder;
use AlexFigures\JsonApi\Http\Cache\ConditionalRequestEvaluator;
use AlexFigures\JsonApi\Http\Cache\EtagGeneratorInterface;
use AlexFigures\JsonApi\Http\Cache\HeadersApplier;
use AlexFigures\JsonApi\Http\Cache\LastModifiedResolver;
use AlexFigures\JsonApi\Http\Cache\SurrogateKeyBuilder;
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
 * @internal
 */
final readonly class CachePreconditionsSubscriber implements EventSubscriberInterface
{
    /**
     * @param CachePreconditionsConfig $config
     */
    public function __construct(
        array $config,
        private CacheKeyBuilder $cacheKeyBuilder,
        private EtagGeneratorInterface $etagGenerator,
        private LastModifiedResolver $lastModified,
        private ConditionalRequestEvaluator $conditional,
        private HeadersApplier $headers,
        private SurrogateKeyBuilder $surrogates,
        private ?\AlexFigures\JsonApi\Http\Controller\ResourceController $resource = null,
        private ?\AlexFigures\JsonApi\Http\Controller\RelationshipGetController $relationship = null,
        private ?\AlexFigures\JsonApi\Contract\Data\WriteConcurrencyGuardInterface $concurrency = null,
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
            KernelEvents::CONTROLLER => ['onKernelController', 0],
            KernelEvents::CONTROLLER_ARGUMENTS => ['onKernelControllerArguments', 0],
            KernelEvents::RESPONSE => ['onKernelResponse', 0],
        ];
    }

    public function onKernelController(\Symfony\Component\HttpKernel\Event\ControllerEvent $event): void
    {
        if ($this->shouldEvaluate($event->getRequest(), $event->getController(), $event->isMainRequest())) {
            $controller = $event->getController();
            $handler = is_array($controller) ? $controller[0] : $controller;
            if ($handler instanceof \AlexFigures\JsonApi\Http\Controller\RelationshipWriteController) {
                $request = $event->getRequest();
                $type = $request->attributes->get('type');
                $id = $request->attributes->get('id');
                $rel = $request->attributes->get('rel', $request->attributes->get('relationship'));
                if (is_string($type) && is_string($id) && is_string($rel)) {
                    $handler->assertAccess($request, $type, $id, $rel);
                }
            }
        }
        if ($this->concurrency !== null) {
            return;
        }
        $this->evaluateBeforeWrite($event->getRequest(), $event->getController(), $event->isMainRequest());
    }

    public function onKernelControllerArguments(\Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent $event): void
    {
        if ($this->concurrency === null || !$this->shouldEvaluate($event->getRequest(), $event->getController(), $event->isMainRequest())) {
            return;
        }
        $request = $event->getRequest();
        $controller = $event->getController();
        $type = $request->attributes->get('type');
        $id = $request->attributes->get('id');
        \assert(is_string($type) && is_string($id));
        // Arguments are already resolved; keep the original controller's parameter contract.
        $event->setController(fn (...$arguments) => $this->concurrency->protect($type, $id, function () use ($request, $controller, $arguments) {
            $this->evaluateBeforeWrite($request, $controller, true);
            return $controller(...$arguments);
        }));
    }

    /** @param callable(mixed ...):mixed $controller */
    private function shouldEvaluate(Request $request, callable $controller, bool $main): bool
    {
        if (!$this->enabled || !$main || !$this->requiresPreconditions($request) || !$this->conditional->needsWriteEvaluation($request)) {
            return false;
        }
        if (!is_string($request->attributes->get('type')) || !is_string($request->attributes->get('id')) || $this->resource === null) {
            return false;
        }
        $handler = is_array($controller) ? $controller[0] : $controller;
        return $handler instanceof \AlexFigures\JsonApi\Http\Controller\UpdateResourceController
            || $handler instanceof \AlexFigures\JsonApi\Http\Controller\DeleteResourceController
            || $handler instanceof \AlexFigures\JsonApi\Http\Controller\RelationshipWriteController;
    }

    /** @param callable(mixed ...):mixed $controller */
    private function evaluateBeforeWrite(Request $request, callable $controller, bool $main): void
    {
        if (!$this->shouldEvaluate($request, $controller, $main)) {
            return;
        }
        $type = $request->attributes->get('type');
        $id = $request->attributes->get('id');
        if (!is_string($type) || !is_string($id) || $this->resource === null) {
            return;
        }
        $read = clone $request;
        $read->setMethod('GET');
        $rel = $request->attributes->get('rel', $request->attributes->get('relationship'));
        $current = is_string($rel) && $this->relationship !== null
            ? $this->relationship->currentRepresentation($read, $type, $id, $rel)
            : $this->resource->currentRepresentation($read, $type, $id);
        $etag = $this->etagGenerator->generate($read, $current, $this->cacheKeyBuilder->build($read), false);
        $this->conditional->evaluate($request, $current, $etag, $this->lastModified->resolve($read, $current));
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$this->enabled || !$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

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

        return in_array($method, ['PATCH', 'PUT', 'DELETE', 'POST'], true);
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
        $route = $request->attributes->get('_route');
        return is_string($route) && ($route === 'jsonapi.collection' || str_ends_with($route, '.index'));
    }

    private function resolveLastModified(Request $request, Response $response): ?DateTimeImmutable
    {
        return $this->lastModified->resolve($request, $response);
    }
}
