<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Functional\Http;

use JsonApi\Symfony\Tests\Functional\JsonApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * GAP-012: HEAD Request Support
 *
 * Tests that HEAD requests are properly supported:
 * - HEAD returns same headers as GET
 * - HEAD returns empty body
 * - HEAD returns appropriate status code
 * - HEAD works for all resource endpoints
 */
final class HeadRequestTest extends JsonApiTestCase
{
    public function testHeadRequestReturnsHeadersWithoutBody(): void
    {
        // First, make a GET request to get expected headers
        $getRequest = Request::create('/api/articles/1', 'GET');
        $getResponse = $this->resourceController()($getRequest, 'articles', '1');

        // Now make a HEAD request
        $headRequest = Request::create('/api/articles/1', 'HEAD');
        $headResponse = $this->resourceController()($headRequest, 'articles', '1');

        // Status code should be the same
        self::assertSame($getResponse->getStatusCode(), $headResponse->getStatusCode());
        self::assertSame(Response::HTTP_OK, $headResponse->getStatusCode());

        // Content-Type should be present
        self::assertSame(
            $getResponse->headers->get('Content-Type'),
            $headResponse->headers->get('Content-Type')
        );

        // Body should be empty for HEAD
        self::assertEmpty($headResponse->getContent());

        // But GET should have content
        self::assertNotEmpty($getResponse->getContent());
    }

    public function testHeadRequestForCollection(): void
    {
        $headRequest = Request::create('/api/articles', 'HEAD');
        $headResponse = $this->collectionController()($headRequest, 'articles');

        self::assertSame(Response::HTTP_OK, $headResponse->getStatusCode());
        self::assertSame('application/vnd.api+json', $headResponse->headers->get('Content-Type'));
        self::assertEmpty($headResponse->getContent());
    }

    public function testHeadRequestForRelationship(): void
    {
        $headRequest = Request::create('/api/articles/1/relationships/author', 'HEAD');
        $headResponse = $this->relationshipGetController()($headRequest, 'articles', '1', 'author');

        self::assertSame(Response::HTTP_OK, $headResponse->getStatusCode());
        self::assertSame('application/vnd.api+json', $headResponse->headers->get('Content-Type'));
        self::assertEmpty($headResponse->getContent());
    }

    public function testHeadRequestForRelated(): void
    {
        $headRequest = Request::create('/api/articles/1/author', 'HEAD');
        $headResponse = $this->relatedController()($headRequest, 'articles', '1', 'author');

        self::assertSame(Response::HTTP_OK, $headResponse->getStatusCode());
        self::assertSame('application/vnd.api+json', $headResponse->headers->get('Content-Type'));
        self::assertEmpty($headResponse->getContent());
    }

    public function testHeadRequestWithQueryParameters(): void
    {
        // HEAD should respect query parameters like GET
        $headRequest = Request::create('/api/articles?fields[articles]=title&page[size]=5', 'HEAD');
        $headResponse = $this->collectionController()($headRequest, 'articles');

        self::assertSame(Response::HTTP_OK, $headResponse->getStatusCode());
        self::assertSame('application/vnd.api+json', $headResponse->headers->get('Content-Type'));
        self::assertEmpty($headResponse->getContent());
    }

    public function testHeadRequestFor404(): void
    {
        // HEAD should return 404 for non-existent resources
        $headRequest = Request::create('/api/articles/999999', 'HEAD');

        try {
            $this->resourceController()($headRequest, 'articles', '999999');
            self::fail('Expected NotFoundException to be thrown');
        } catch (\Throwable $exception) {
            $response = $this->handleException($headRequest, $exception);
            self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        }
    }

    public function testHeadRequestContentLengthHeader(): void
    {
        $getRequest = Request::create('/api/articles/1', 'GET');
        $getResponse = $this->resourceController()($getRequest, 'articles', '1');

        $headRequest = Request::create('/api/articles/1', 'HEAD');
        $headResponse = $this->resourceController()($headRequest, 'articles', '1');

        // HEAD should work and return 200
        self::assertSame(Response::HTTP_OK, $headResponse->getStatusCode());
        self::assertEmpty($headResponse->getContent());

        // If GET has Content-Length, HEAD should have the same
        if ($getResponse->headers->has('Content-Length')) {
            self::assertSame(
                $getResponse->headers->get('Content-Length'),
                $headResponse->headers->get('Content-Length')
            );
        }
    }
}
