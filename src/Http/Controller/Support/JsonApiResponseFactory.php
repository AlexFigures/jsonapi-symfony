<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Controller\Support;

use AlexFigures\Symfony\Http\Negotiation\MediaType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Factory for creating JSON:API compliant HTTP responses.
 *
 * This service ensures all responses have the correct Content-Type header
 * and follow JSON:API specification requirements.
 */
final class JsonApiResponseFactory
{
    /**
     * Create a JSON:API response.
     *
     * @param array<string, mixed> $document JSON:API document
     * @param int                  $status   HTTP status code
     * @param bool                 $isHead   Whether this is a HEAD request (omit body)
     */
    public function create(
        array $document,
        int $status = Response::HTTP_OK,
        bool $isHead = false
    ): JsonResponse {
        $response = new JsonResponse($document, $status);
        $response->headers->set('Content-Type', MediaType::JSON_API);

        if ($isHead) {
            $response->setContent('');
        }

        return $response;
    }

    /**
     * Create a 201 Created response with Location header.
     *
     * @param array<string, mixed> $document JSON:API document
     * @param string               $location URL of the created resource
     */
    public function created(array $document, string $location): JsonResponse
    {
        $response = $this->create($document, Response::HTTP_CREATED);
        $response->headers->set('Location', $location);

        return $response;
    }

    /**
     * Create a 204 No Content response.
     */
    public function noContent(): Response
    {
        return new Response('', Response::HTTP_NO_CONTENT);
    }
}

