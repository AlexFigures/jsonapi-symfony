<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\JsonApiStatus;

use AlexFigures\Symfony\Http\Negotiation\MediaType;
use AlexFigures\Symfony\Tests\Functional\JsonApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class RelationshipMutationStatusTest extends JsonApiTestCase
{
    public function testPatchRelationshipReturns200WithLinkageDocument(): void
    {
        $payload = [
            'data' => [
                'type' => 'authors',
                'id' => '1',
            ],
        ];

        $request = $this->jsonRequest('PATCH', '/api/articles/1/relationships/author', $payload);
        $response = ($this->relationshipWriteController())($request, 'articles', '1', 'author');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $document = $this->decode($response);
        self::assertSame(['type' => 'authors', 'id' => '1'], $document['data']);
    }

    public function testPatchRelationshipClearingReturns200(): void
    {
        $payload = ['data' => []];
        $request = $this->jsonRequest('PATCH', '/api/articles/1/relationships/tags', $payload);

        $response = ($this->relationshipWriteController())($request, 'articles', '1', 'tags');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $document = $this->decode($response);
        self::assertSame([], $document['data']);
    }

    public function testRelationshipOperationForbiddenIsNotApplicable(): void
    {
        self::markTestSkipped('Bundle does not currently expose per-relationship authorization returning 403.');
    }

    public function testRelationshipAsyncAcceptedIsNotApplicable(): void
    {
        self::markTestSkipped('Relationship mutation 202 workflows are not implemented.');
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
