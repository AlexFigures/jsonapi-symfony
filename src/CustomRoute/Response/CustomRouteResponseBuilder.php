<?php

declare(strict_types=1);

namespace JsonApi\Symfony\CustomRoute\Response;

use JsonApi\Symfony\Contract\Data\Slice;
use JsonApi\Symfony\CustomRoute\Context\CustomRouteContext;
use JsonApi\Symfony\CustomRoute\Result\CustomRouteResult;
use JsonApi\Symfony\Http\Document\DocumentBuilder;
use JsonApi\Symfony\Http\Error\ErrorBuilder;
use JsonApi\Symfony\Http\Error\ErrorCodes;
use JsonApi\Symfony\Http\Link\LinkGenerator;
use JsonApi\Symfony\Http\Negotiation\MediaType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Builds Symfony Response objects from CustomRouteResult instances.
 *
 * This builder is responsible for:
 * - Converting CustomRouteResult to proper JSON:API responses
 * - Integrating with DocumentBuilder for resource/collection formatting
 * - Formatting error responses as JSON:API error documents
 * - Setting proper HTTP headers and status codes
 * - Adding custom meta and links from the result
 *
 * @internal
 */
final class CustomRouteResponseBuilder
{
    public function __construct(
        private readonly DocumentBuilder $documentBuilder,
        private readonly LinkGenerator $linkGenerator,
        private readonly ErrorBuilder $errorBuilder,
    ) {
    }

    /**
     * Build a Symfony Response from a CustomRouteResult.
     *
     * @param CustomRouteResult  $result  The result from the handler
     * @param CustomRouteContext $context The request context
     *
     * @return Response The Symfony HTTP response
     */
    public function build(CustomRouteResult $result, CustomRouteContext $context): Response
    {
        if ($result->isNoContent()) {
            return $this->buildNoContentResponse($result);
        }

        if ($result->isError()) {
            return $this->buildErrorResponse($result);
        }

        if ($result->isResource()) {
            return $this->buildResourceResponse($result, $context);
        }

        if ($result->isCollection()) {
            return $this->buildCollectionResponse($result, $context);
        }

        // This should never happen, but handle it gracefully
        return $this->buildInternalErrorResponse('Unknown result type');
    }

    /**
     * Build a 204 No Content response.
     */
    private function buildNoContentResponse(CustomRouteResult $result): Response
    {
        $headers = array_merge(
            ['Content-Type' => MediaType::JSON_API],
            $result->getHeaders()
        );

        return new Response(null, $result->getStatus(), $headers);
    }

    /**
     * Build an error response with JSON:API error document.
     */
    private function buildErrorResponse(CustomRouteResult $result): JsonResponse
    {
        $data = $result->getData();
        $errors = [];

        if (is_array($data)) {
            // Handle validation errors (array of errors)
            if (isset($data[0]) && is_array($data[0])) {
                // Multiple errors
                foreach ($data as $errorData) {
                    $errors[] = $this->buildErrorObject($errorData, $result->getStatus());
                }
            } else {
                // Single error
                $errors[] = $this->buildErrorObject($data, $result->getStatus());
            }
        }

        $document = [
            'jsonapi' => ['version' => '1.1'],
            'errors' => $errors,
        ];

        // Add custom links if provided
        if ($result->getLinks() !== []) {
            $document['links'] = $result->getLinks();
        }

        // Add custom meta if provided
        if ($result->getMeta() !== []) {
            $document['meta'] = $result->getMeta();
        }

        $headers = array_merge(
            [
                'Content-Type' => MediaType::JSON_API,
                'Vary' => 'Accept',
            ],
            $result->getHeaders()
        );

        return new JsonResponse($document, $result->getStatus(), $headers);
    }

    /**
     * Build a single resource response.
     */
    private function buildResourceResponse(CustomRouteResult $result, CustomRouteContext $context): JsonResponse
    {
        $resource = $result->getData();

        if (!is_object($resource)) {
            return $this->buildInternalErrorResponse('Resource result must contain an object');
        }

        // Build JSON:API document using DocumentBuilder
        $document = $this->documentBuilder->buildResource(
            $context->getResourceType(),
            $resource,
            $context->getCriteria(),
            $context->getRequest()
        );

        // Merge custom meta from result
        if ($result->getMeta() !== []) {
            $document['meta'] = array_merge($document['meta'] ?? [], $result->getMeta());
        }

        // Merge custom links from result
        if ($result->getLinks() !== []) {
            $document['links'] = array_merge($document['links'] ?? [], $result->getLinks());
        }

        $headers = array_merge(
            ['Content-Type' => MediaType::JSON_API],
            $result->getHeaders()
        );

        // Add Location header for 201 Created responses
        if ($result->getStatus() === Response::HTTP_CREATED) {
            $resourceId = $document['data']['id'] ?? null;
            if ($resourceId !== null) {
                $headers['Location'] = $this->linkGenerator->resourceSelf(
                    $context->getResourceType(),
                    (string) $resourceId
                );
            }
        }

        return new JsonResponse($document, $result->getStatus(), $headers);
    }

    /**
     * Build a collection response.
     */
    private function buildCollectionResponse(CustomRouteResult $result, CustomRouteContext $context): JsonResponse
    {
        $resources = $result->getData();

        if (!is_array($resources)) {
            return $this->buildInternalErrorResponse('Collection result must contain an array');
        }

        $totalItems = $result->getTotalItems() ?? count($resources);
        $criteria = $context->getCriteria();

        // Create a Slice for pagination
        $slice = new Slice(
            items: $resources,
            totalItems: $totalItems,
            pageNumber: $criteria->pagination->number,
            pageSize: $criteria->pagination->size,
        );

        // Build JSON:API document using DocumentBuilder
        $document = $this->documentBuilder->buildCollection(
            $context->getResourceType(),
            $resources,
            $criteria,
            $slice,
            $context->getRequest()
        );

        // Merge custom meta from result
        if ($result->getMeta() !== []) {
            $document['meta'] = array_merge($document['meta'] ?? [], $result->getMeta());
        }

        // Merge custom links from result
        if ($result->getLinks() !== []) {
            $document['links'] = array_merge($document['links'] ?? [], $result->getLinks());
        }

        $headers = array_merge(
            ['Content-Type' => MediaType::JSON_API],
            $result->getHeaders()
        );

        return new JsonResponse($document, $result->getStatus(), $headers);
    }

    /**
     * Build an error object from error data.
     *
     * @param array<string, mixed> $errorData
     */
    private function buildErrorObject(array $errorData, int $status): array
    {
        $statusStr = (string) $status;
        $detail = $errorData['detail'] ?? 'An error occurred';
        $pointer = $errorData['pointer'] ?? null;

        $error = [
            'status' => $errorData['status'] ?? $statusStr,
            'code' => $this->resolveErrorCode($status),
            'title' => $this->resolveErrorTitle($status),
            'detail' => $detail,
        ];

        if ($pointer !== null) {
            $error['source'] = ['pointer' => $pointer];
        }

        return $error;
    }

    /**
     * Resolve error code from HTTP status.
     */
    private function resolveErrorCode(int $status): string
    {
        return match ($status) {
            400 => ErrorCodes::INVALID_PARAMETER,
            403 => ErrorCodes::FORBIDDEN,
            404 => ErrorCodes::RESOURCE_NOT_FOUND,
            409 => ErrorCodes::CONFLICT,
            422 => ErrorCodes::VALIDATION_ERROR,
            default => ErrorCodes::INTERNAL_SERVER_ERROR,
        };
    }

    /**
     * Resolve error title from HTTP status.
     */
    private function resolveErrorTitle(int $status): string
    {
        return match ($status) {
            400 => 'Bad Request',
            403 => 'Forbidden',
            404 => 'Not Found',
            409 => 'Conflict',
            422 => 'Unprocessable Entity',
            default => 'Internal Server Error',
        };
    }

    /**
     * Build a 500 Internal Server Error response.
     */
    private function buildInternalErrorResponse(string $detail): JsonResponse
    {
        $error = $this->errorBuilder->create(
            '500',
            ErrorCodes::INTERNAL_SERVER_ERROR,
            'Internal Server Error',
            $detail
        );

        $document = [
            'jsonapi' => ['version' => '1.1'],
            'errors' => [$error->toArray()],
        ];

        return new JsonResponse(
            $document,
            Response::HTTP_INTERNAL_SERVER_ERROR,
            [
                'Content-Type' => MediaType::JSON_API,
                'Vary' => 'Accept',
            ]
        );
    }
}
