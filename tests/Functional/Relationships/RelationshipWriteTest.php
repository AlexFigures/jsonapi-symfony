<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Functional\Relationships;

use JsonApi\Symfony\Http\Exception\ConflictException;
use JsonApi\Symfony\Http\Exception\JsonApiHttpException;
use JsonApi\Symfony\Http\Exception\NotFoundException;
use JsonApi\Symfony\Tests\Functional\JsonApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

final class RelationshipWriteTest extends JsonApiTestCase
{
    public function testPostToManyAddsIdentifiers(): void
    {
        $payload = json_encode([
            'data' => [
                ['type' => 'tags', 'id' => '3'],
                ['type' => 'tags', 'id' => '1'],
            ],
        ], \JSON_THROW_ON_ERROR);

        $request = $this->relationshipRequest('POST', '/api/articles/1/relationships/tags', $payload);

        $response = $this->relationshipWriteController()($request, 'articles', '1', 'tags');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{data: list<array{type: string, id: string}>} $document */
        $document = $this->decode($response);
        self::assertSame(['tags', 'tags', 'tags'], array_column($document['data'], 'type'));
        self::assertSame(['1', '2', '3'], array_column($document['data'], 'id'));
    }

    public function testDeleteToManyRemovesIdentifiers(): void
    {
        $payload = json_encode([
            'data' => [
                ['type' => 'tags', 'id' => '2'],
                ['type' => 'tags', 'id' => '4'],
            ],
        ], \JSON_THROW_ON_ERROR);

        $request = $this->relationshipRequest('DELETE', '/api/articles/1/relationships/tags', $payload);

        $response = $this->relationshipWriteController()($request, 'articles', '1', 'tags');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{data: list<array{type: string, id: string}>} $document */
        $document = $this->decode($response);
        self::assertSame(['1'], array_column($document['data'], 'id'));
    }

    public function testPatchToManyReplacesIdentifiers(): void
    {
        $payload = json_encode([
            'data' => [
                ['type' => 'tags', 'id' => '3'],
                ['type' => 'tags', 'id' => '4'],
            ],
        ], \JSON_THROW_ON_ERROR);

        $request = $this->relationshipRequest('PATCH', '/api/articles/1/relationships/tags', $payload);

        $response = $this->relationshipWriteController()($request, 'articles', '1', 'tags');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{data: list<array{type: string, id: string}>} $document */
        $document = $this->decode($response);
        self::assertSame(['3', '4'], array_column($document['data'], 'id'));
    }

    public function testPatchToOneUpdatesRelationship(): void
    {
        $payload = json_encode([
            'data' => ['type' => 'authors', 'id' => '2'],
        ], \JSON_THROW_ON_ERROR);

        $request = $this->relationshipRequest('PATCH', '/api/articles/1/relationships/author', $payload);

        $response = $this->relationshipWriteController()($request, 'articles', '1', 'author');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{data: array{type: string, id: string}} $document */
        $document = $this->decode($response);
        self::assertSame(['type' => 'authors', 'id' => '2'], $document['data']);
    }

    public function testPatchToOneNullWhenNotNullableReturnsConflict(): void
    {
        $payload = json_encode([
            'data' => null,
        ], \JSON_THROW_ON_ERROR);

        $request = $this->relationshipRequest('PATCH', '/api/articles/1/relationships/author', $payload);
        $this->expectException(ConflictException::class);
        ($this->relationshipWriteController())($request, 'articles', '1', 'author');
    }

    public function testPostToOneReturnsMethodNotAllowed(): void
    {
        $payload = json_encode([
            'data' => ['type' => 'authors', 'id' => '2'],
        ], \JSON_THROW_ON_ERROR);

        $request = $this->relationshipRequest('POST', '/api/articles/1/relationships/author', $payload);
        $this->expectException(MethodNotAllowedHttpException::class);
        ($this->relationshipWriteController())($request, 'articles', '1', 'author');
    }

    public function testMismatchedTypeCausesConflict(): void
    {
        $payload = json_encode([
            'data' => [
                ['type' => 'authors', 'id' => '1'],
            ],
        ], \JSON_THROW_ON_ERROR);

        $request = $this->relationshipRequest('PATCH', '/api/articles/1/relationships/tags', $payload);

        $this->expectException(ConflictException::class);
        ($this->relationshipWriteController())($request, 'articles', '1', 'tags');
    }

    public function testUnknownIdentifierReturnsNotFound(): void
    {
        $payload = json_encode([
            'data' => [
                ['type' => 'tags', 'id' => '999'],
            ],
        ], \JSON_THROW_ON_ERROR);

        $request = $this->relationshipRequest('POST', '/api/articles/1/relationships/tags', $payload);

        $this->expectException(NotFoundException::class);
        ($this->relationshipWriteController())($request, 'articles', '1', 'tags');
    }

    public function testUnsupportedContentTypeReturnsError(): void
    {
        $request = Request::create(
            '/api/articles/1/relationships/tags',
            'PATCH',
            server: ['CONTENT_TYPE' => 'text/plain'],
            content: '{}'
        );

        $this->expectException(JsonApiHttpException::class);
        ($this->relationshipWriteController())($request, 'articles', '1', 'tags');
    }

    private function relationshipRequest(string $method, string $uri, string $payload): Request
    {
        return Request::create(
            $uri,
            $method,
            server: ['CONTENT_TYPE' => 'application/vnd.api+json'],
            content: $payload,
        );
    }
}
