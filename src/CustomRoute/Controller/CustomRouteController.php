<?php

declare(strict_types=1);

namespace JsonApi\Symfony\CustomRoute\Controller;

use JsonApi\Symfony\Contract\Tx\TransactionManager;
use JsonApi\Symfony\CustomRoute\Attribute\NoTransaction;
use JsonApi\Symfony\CustomRoute\Context\CustomRouteContextFactory;
use JsonApi\Symfony\CustomRoute\Handler\CustomRouteHandlerInterface;
use JsonApi\Symfony\CustomRoute\Handler\CustomRouteHandlerRegistry;
use JsonApi\Symfony\CustomRoute\Response\CustomRouteResponseBuilder;
use JsonApi\Symfony\CustomRoute\Result\CustomRouteResult;
use JsonApi\Symfony\Events\ResourceChangedEvent;
use JsonApi\Symfony\Http\Error\ErrorBuilder;
use JsonApi\Symfony\Http\Error\ErrorCodes;
use JsonApi\Symfony\Http\Exception\JsonApiHttpException;
use JsonApi\Symfony\Http\Negotiation\MediaType;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;

/**
 * Generic controller for custom route handlers.
 *
 * This controller is responsible for:
 * - Resolving the handler for the route
 * - Creating the context from the request
 * - Executing the handler (with automatic transaction wrapping)
 * - Converting the result to a JSON:API response
 * - Handling exceptions and converting them to JSON:API errors
 * - Dispatching resource changed events
 *
 * This is the entry point for all handler-based custom routes.
 *
 * @internal
 */
final class CustomRouteController
{
    public function __construct(
        private readonly CustomRouteHandlerRegistry $handlerRegistry,
        private readonly CustomRouteContextFactory $contextFactory,
        private readonly CustomRouteResponseBuilder $responseBuilder,
        private readonly TransactionManager $transactionManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ErrorBuilder $errorBuilder,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    /**
     * Handle a custom route request.
     *
     * This is the main entry point for all handler-based custom routes.
     * The route name is used to resolve the handler, and the handler is
     * executed within a transaction.
     *
     * @param Request $request The HTTP request
     * @param string $routeName The route name (e.g., 'articles.publish')
     *
     * @return Response The HTTP response
     */
    public function __invoke(Request $request, string $routeName): Response
    {
        try {
            // Resolve the handler for this route
            $handler = $this->handlerRegistry->get($routeName);
            
            // Create the context from the request
            $context = $this->contextFactory->create($request, $routeName);
            
            $this->logger->debug('Executing custom route handler', [
                'route' => $routeName,
                'handler' => $handler::class,
                'resourceType' => $context->getResourceType(),
                'hasResource' => $context->hasResource(),
            ]);
            
            // Execute the handler (with transaction wrapping)
            $result = $this->executeHandler($handler, $context);
            
            // Dispatch resource changed event if applicable
            $this->dispatchEventIfNeeded($result, $context);
            
            // Build the response
            $response = $this->responseBuilder->build($result, $context);
            
            $this->logger->debug('Custom route handler executed successfully', [
                'route' => $routeName,
                'status' => $response->getStatusCode(),
            ]);
            
            return $response;
            
        } catch (JsonApiHttpException $e) {
            // JSON:API exceptions are already properly formatted, just re-throw
            throw $e;
        } catch (Throwable $e) {
            // Convert unexpected exceptions to JSON:API errors
            $this->logger->error('Custom route handler failed', [
                'route' => $routeName,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $this->convertToJsonApiException($e);
        }
    }

    /**
     * Execute the handler with automatic transaction wrapping.
     *
     * Handlers are executed within a transaction by default. If the handler
     * throws an exception or returns an error result, the transaction is
     * rolled back.
     *
     * To opt-out of transaction wrapping, use the #[NoTransaction] attribute
     * on the handler class.
     */
    private function executeHandler(
        CustomRouteHandlerInterface $handler,
        $context
    ): CustomRouteResult {
        // Check if handler should be executed without transaction
        if ($this->shouldSkipTransaction($handler)) {
            return $handler->handle($context);
        }

        // Execute within transaction
        try {
            return $this->transactionManager->transactional(
                function () use ($handler, $context): CustomRouteResult {
                    $result = $handler->handle($context);

                    // Rollback transaction if handler returned an error
                    if ($result->isError()) {
                        throw new HandlerReturnedErrorException($result);
                    }

                    return $result;
                }
            );
        } catch (HandlerReturnedErrorException $e) {
            // Handler returned an error result, transaction was rolled back
            // Return the error result to be formatted as JSON:API error
            return $e->getResult();
        }
    }

    /**
     * Check if handler should skip transaction wrapping.
     *
     * Handlers with the #[NoTransaction] attribute are executed without
     * transaction wrapping. This is useful for read-only operations.
     */
    private function shouldSkipTransaction(CustomRouteHandlerInterface $handler): bool
    {
        $reflection = new \ReflectionClass($handler);
        $attributes = $reflection->getAttributes(NoTransaction::class);
        
        return count($attributes) > 0;
    }

    /**
     * Dispatch ResourceChangedEvent if the result indicates a resource change.
     *
     * Events are dispatched for:
     * - 201 Created (create operation)
     * - 200 OK with resource (update operation)
     * - 204 No Content (delete operation)
     */
    private function dispatchEventIfNeeded(
        CustomRouteResult $result,
        $context
    ): void {
        $status = $result->getStatus();
        $resourceType = $context->getResourceType();
        
        // Determine operation type based on status and result type
        $operation = null;
        $resourceId = null;
        
        if ($status === Response::HTTP_CREATED && $result->isResource()) {
            $operation = 'create';
            $resourceId = $this->extractResourceId($result->getData());
        } elseif ($status === Response::HTTP_OK && $result->isResource() && $context->hasResource()) {
            $operation = 'update';
            // Use route parameter for updates (handlers may return DTOs without id property)
            $resourceId = $context->getParam('id');
        } elseif ($status === Response::HTTP_NO_CONTENT && $context->hasResource()) {
            $operation = 'delete';
            $resourceId = $context->getParam('id');
        }
        
        if ($operation !== null && $resourceId !== null) {
            $this->eventDispatcher->dispatch(
                new ResourceChangedEvent($resourceType, (string) $resourceId, $operation)
            );
            
            $this->logger->debug('Dispatched resource changed event', [
                'resourceType' => $resourceType,
                'resourceId' => $resourceId,
                'operation' => $operation,
            ]);
        }
    }

    /**
     * Extract resource ID from a resource object.
     */
    private function extractResourceId(mixed $resource): ?string
    {
        if (!is_object($resource)) {
            return null;
        }

        // Try common ID property names
        foreach (['id', 'getId'] as $property) {
            if (property_exists($resource, $property)) {
                return (string) $resource->$property;
            }
            
            if (method_exists($resource, $property)) {
                return (string) $resource->$property();
            }
        }

        return null;
    }

    /**
     * Convert a generic exception to a JSON:API exception.
     */
    private function convertToJsonApiException(Throwable $e): JsonApiHttpException
    {
        $error = $this->errorBuilder->create(
            '500',
            ErrorCodes::INTERNAL_SERVER_ERROR,
            'Internal Server Error',
            $e->getMessage()
        );

        return new JsonApiHttpException(
            500,
            'An unexpected error occurred',
            ['Content-Type' => MediaType::JSON_API],
            [$error],
            $e
        );
    }
}

/**
 * Internal exception used to signal that a handler returned an error result.
 *
 * This is used to trigger transaction rollback when a handler returns an
 * error result instead of throwing an exception.
 *
 * @internal
 */
final class HandlerReturnedErrorException extends \RuntimeException
{
    public function __construct(
        private readonly CustomRouteResult $result,
    ) {
        parent::__construct('Handler returned an error result');
    }

    public function getResult(): CustomRouteResult
    {
        return $this->result;
    }
}

