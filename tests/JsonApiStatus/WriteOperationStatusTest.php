<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\JsonApiStatus;

use AlexFigures\Symfony\Http\Exception\ConflictException;
use AlexFigures\Symfony\Http\Exception\ForbiddenException;
use AlexFigures\Symfony\Http\Negotiation\MediaType;
use AlexFigures\Symfony\Tests\Functional\JsonApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class WriteOperationStatusTest extends JsonApiTestCase
{
    public function testPostAssignsServerIdReturns201AndLocation(): void
    {
        $payload = [
            'data' => [
                'type' => 'articles',
                'attributes' => [
                    'title' => 'Status Spec Article',
                ],
            ],
        ];

        $request = $this->jsonRequest('POST', '/api/articles', $payload);
        $response = ($this->createController())($request, 'articles');

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        self::assertSame(MediaType::JSON_API, $response->headers->get('Content-Type'));

        $document = $this->decode($response);
        self::assertArrayHasKey('data', $document);
        self::assertNotEmpty($document['data']['id'] ?? null);

        $selfLink = $document['data']['links']['self'] ?? null;
        if ($selfLink !== null) {
            self::assertSame($selfLink, $response->headers->get('Location'));
        }
    }

    public function testPostWithClientGeneratedIdAllowedReturns201(): void
    {
        $payload = [
            'data' => [
                'type' => 'authors',
                'id' => 'status-author-201',
                'attributes' => [
                    'name' => 'Status Author 201',
                ],
            ],
        ];

        $request = $this->jsonRequest('POST', '/api/authors', $payload);
        $response = ($this->createController())($request, 'authors');

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $document = $this->decode($response);
        self::assertSame('status-author-201', $document['data']['id']);
    }

    public function testPostClientGeneratedIdForbiddenReturns403(): void
    {
        $payload = [
            'data' => [
                'type' => 'articles',
                'id' => 'manual-id',
                'attributes' => [
                    'title' => 'Manual Id',
                ],
            ],
        ];

        $request = $this->jsonRequest('POST', '/api/articles', $payload);

        try {
            ($this->createController())($request, 'articles');
            self::fail('Expected ForbiddenException (403) when client-generated IDs are not allowed.');
        } catch (ForbiddenException $exception) {
            self::assertSame(403, $exception->getStatusCode());
            $response = $this->handleException($request, $exception);
            self::assertSame(403, $response->getStatusCode());
            $errors = $this->decode($response);
            self::assertArrayHasKey('errors', $errors);
        }
    }

    public function testPostClientGeneratedIdConflictReturns409(): void
    {
        $payload = [
            'data' => [
                'type' => 'authors',
                'id' => 'status-author-conflict',
                'attributes' => [
                    'name' => 'Status Author Conflict',
                ],
            ],
        ];

        $request = $this->jsonRequest('POST', '/api/authors', $payload);
        ($this->createController())($request, 'authors');

        try {
            ($this->createController())($request, 'authors');
            self::fail('Expected ConflictException (409) when client-generated id already exists.');
        } catch (ConflictException $exception) {
            self::assertSame(409, $exception->getStatusCode());
            $response = $this->handleException($request, $exception);
            self::assertSame(409, $response->getStatusCode());
            $errors = $this->decode($response);
            self::assertArrayHasKey('errors', $errors);
        }
    }

    public function testPostTypeMismatchReturns409(): void
    {
        $payload = [
            'data' => [
                'type' => 'authors',
                'attributes' => [
                    'name' => 'Type Mismatch',
                ],
            ],
        ];

        $request = $this->jsonRequest('POST', '/api/articles', $payload);

        try {
            ($this->createController())($request, 'articles');
            self::fail('Expected ConflictException (409) due to type mismatch.');
        } catch (ConflictException $exception) {
            self::assertSame(409, $exception->getStatusCode());
        }
    }

    public function testPostAsyncAcceptedIsNotApplicable(): void
    {
        self::markTestSkipped('202 Accepted flow is not implemented by the bundle (no async creation endpoints).');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonRequest(string $method, string $uri, array $payload): Request
    {
        $json = json_encode($payload, \JSON_THROW_ON_ERROR);

        return Request::create(
            $uri,
            $method,
            server: [
                'CONTENT_TYPE' => MediaType::JSON_API,
                'HTTP_ACCEPT' => MediaType::JSON_API,
            ],
            content: $json,
        );
    }
}
