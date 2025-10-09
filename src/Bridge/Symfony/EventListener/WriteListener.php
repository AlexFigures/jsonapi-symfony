<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Bridge\Symfony\EventListener;

use JsonApi\Symfony\Bridge\Doctrine\Flush\FlushManager;
use JsonApi\Symfony\Http\Validation\DatabaseErrorMapper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Automatically flushes Doctrine changes after write operations.
 *
 * This listener implements the "deferred flush" pattern inspired by API Platform:
 * - Controllers/handlers prepare entities (persist/remove) without flushing
 * - This listener flushes all changes after controller execution
 * - Allows Doctrine's CommitOrderCalculator to properly order entity insertions
 *
 * The listener only flushes for write operations (POST, PATCH, DELETE) and
 * only if a flush was scheduled via FlushManager::scheduleFlush().
 *
 * @internal This listener is registered automatically by the bundle
 */
final class WriteListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly FlushManager $flushManager,
        private readonly DatabaseErrorMapper $errorMapper,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Execute after controller, before response is sent
            // Priority -100 ensures this runs after most other listeners
            KernelEvents::VIEW => ['onKernelView', -100],

            // Clear scheduled flush on exception to prevent flushing invalid data
            KernelEvents::EXCEPTION => ['onKernelException', 0],
        ];
    }

    /**
     * Flush Doctrine changes after controller execution.
     *
     * This method is called after the controller has executed and returned
     * a result. It flushes all pending Doctrine changes if:
     * 1. The request is a write operation (POST, PATCH, DELETE)
     * 2. A flush was scheduled via FlushManager::scheduleFlush()
     *
     * Database errors (constraint violations, etc.) are caught and converted
     * to JSON:API error responses.
     */
    public function onKernelView(ViewEvent $event): void
    {
        // Only process main request (not sub-requests)
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Only flush for write operations
        if (!$this->isWriteOperation($request)) {
            return;
        }

        // Extract resource type for error mapping
        $type = $this->extractResourceType($request);

        try {
            // Flush all pending changes
            $this->flushManager->flush();
        } catch (\Throwable $e) {
            // Convert database errors to JSON:API errors
            throw $this->errorMapper->mapDatabaseError($type, $e);
        }
    }

    /**
     * Clear scheduled flush when an exception occurs.
     *
     * This prevents flushing incomplete or invalid data when an error
     * occurs during request processing.
     */
    public function onKernelException(ExceptionEvent $event): void
    {
        // Only process main request
        if (!$event->isMainRequest()) {
            return;
        }

        // Clear the flush flag to prevent flushing on error
        $this->flushManager->clear();
    }

    /**
     * Determine if the request is a write operation.
     *
     * Write operations are:
     * - POST (create resource)
     * - PATCH (update resource)
     * - DELETE (delete resource)
     *
     * Read operations (GET) do not trigger flush.
     */
    private function isWriteOperation(Request $request): bool
    {
        $method = $request->getMethod();

        return in_array($method, ['POST', 'PATCH', 'DELETE'], true);
    }

    /**
     * Extract resource type from request for error mapping.
     *
     * The resource type is used to provide context in error messages.
     * It's extracted from:
     * 1. Route attribute '_jsonapi_resource_type'
     * 2. Request body 'data.type'
     * 3. Fallback to 'unknown'
     */
    private function extractResourceType(Request $request): string
    {
        // Try to get type from route attributes
        $type = $request->attributes->get('_jsonapi_resource_type');
        if (is_string($type) && $type !== '') {
            return $type;
        }

        // Try to get type from request body
        $content = $request->getContent();
        if ($content !== '' && $content !== false) {
            try {
                $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
                if (isset($data['data']['type']) && is_string($data['data']['type'])) {
                    return $data['data']['type'];
                }
            } catch (\JsonException) {
                // Ignore JSON parsing errors
            }
        }

        return 'unknown';
    }
}
