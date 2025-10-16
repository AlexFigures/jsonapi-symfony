<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\JsonApiStatus;

use AlexFigures\Symfony\Http\Exception\ConflictException;
use AlexFigures\Symfony\Http\Exception\NotFoundException;
use AlexFigures\Symfony\Http\Exception\ValidationException;
use AlexFigures\Symfony\Http\Negotiation\MediaType;
use AlexFigures\Symfony\Tests\Functional\JsonApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class UpdateOperationStatusTest extends JsonApiTestCase
{
    public function testPatchReturns200WithDocument(): void
    {
        $payload = [
            'data' => [
                'type' => 'articles',
                'id' => '1',
                'attributes' => [
                    'title' => 'Updated title via status audit',
                ],
            ],
        ];

        $response = ($this->updateController())($this->jsonRequest('PATCH', '/api/articles/1', $payload), 'articles', '1');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $document = $this->decode($response);
        self::assertSame('Updated title via status audit', $document['data']['attributes']['title']);
    }

    public function testPatchUnsupportedOperationIsNotApplicable(): void
    {
        self::markTestSkipped('Bundle does not expose resource-level feature toggles to reject updates (treated as N/A).');
    }

    public function testPatchNonExistingResourceReturns404(): void
    {
        $payload = [
            'data' => [
                'type' => 'articles',
                'id' => 'missing-id',
                'attributes' => [
                    'title' => 'Won\'t update',
                ],
            ],
        ];

        try {
            ($this->updateController())($this->jsonRequest('PATCH', '/api/articles/missing-id', $payload), 'articles', 'missing-id');
            self::fail('Expected NotFoundException (404) when resource is absent.');
        } catch (NotFoundException $exception) {
            self::assertSame(404, $exception->getStatusCode());
        }
    }

    public function testPatchWithUnknownRelatedResourceReturns422(): void
    {
        $payload = [
            'data' => [
                'type' => 'articles',
                'id' => '1',
                'relationships' => [
                    'author' => [
                        'data' => [
                            'type' => 'authors',
                            'id' => 'non-existent-author',
                        ],
                    ],
                ],
            ],
        ];

        try {
            ($this->updateController())($this->jsonRequest('PATCH', '/api/articles/1', $payload), 'articles', '1');
            self::fail('Expected ValidationException (422) when related resource is missing.');
        } catch (ValidationException $exception) {
            self::assertSame(422, $exception->getStatusCode());
        }
    }

    public function testPatchTypeOrIdConflictReturns409(): void
    {
        $payload = [
            'data' => [
                'type' => 'articles',
                'id' => '2',
                'attributes' => [
                    'title' => 'Wrong target',
                ],
            ],
        ];

        try {
            ($this->updateController())($this->jsonRequest('PATCH', '/api/articles/1', $payload), 'articles', '1');
            self::fail('Expected ConflictException (409) when type/id mismatch occurs.');
        } catch (ConflictException $exception) {
            self::assertSame(409, $exception->getStatusCode());
        }
    }

    public function testPatchAsyncAcceptedIsNotApplicable(): void
    {
        self::markTestSkipped('202 Accepted flow is not implemented by the bundle (no async updates).');
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
