<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Functional;

use JsonApi\Symfony\Http\Negotiation\MediaType;
use JsonApi\Symfony\Http\Exception\BadRequestException;
use JsonApi\Symfony\Http\Exception\ConflictException;
use JsonApi\Symfony\Http\Exception\ForbiddenException;
use JsonApi\Symfony\Http\Exception\JsonApiHttpException;
use JsonApi\Symfony\Http\Exception\NotFoundException;
use JsonApi\Symfony\Query\Criteria;
use Symfony\Component\HttpFoundation\Request;

final class ResourceWriteTest extends JsonApiTestCase
{
    public function testCreateArticleGeneratesIdAndReturnsDocument(): void
    {
        $payload = [
            'data' => [
                'type' => 'articles',
                'attributes' => [
                    'title' => 'New post',
                ],
            ],
        ];

        $request = $this->jsonRequest('POST', '/api/articles', $payload);

        $response = ($this->createController())($request, 'articles');

        self::assertSame(201, $response->getStatusCode());
        self::assertSame(MediaType::JSON_API, $response->headers->get('Content-Type'));

        $document = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $id = $document['data']['id'];

        self::assertNotEmpty($id);
        self::assertSame('articles', $document['data']['type']);
        self::assertSame('New post', $document['data']['attributes']['title']);
        self::assertSame($document['data']['links']['self'], $response->headers->get('Location'));

        $stored = $this->repository()->findOne('articles', $id, new Criteria());
        self::assertNotNull($stored);
    }

    public function testCreateAuthorWithClientGeneratedId(): void
    {
        $payload = [
            'data' => [
                'type' => 'authors',
                'id' => 'author-123',
                'attributes' => [
                    'name' => 'Client Author',
                ],
            ],
        ];

        $response = ($this->createController())($this->jsonRequest('POST', '/api/authors', $payload), 'authors');

        self::assertSame(201, $response->getStatusCode());

        $document = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('author-123', $document['data']['id']);
        self::assertSame('Client Author', $document['data']['attributes']['name']);

        $stored = $this->repository()->findOne('authors', 'author-123', new Criteria());
        self::assertNotNull($stored);
    }

    public function testUpdateArticleReturnsUpdatedDocument(): void
    {
        $payload = [
            'data' => [
                'type' => 'articles',
                'id' => '1',
                'attributes' => [
                    'title' => 'Updated title',
                ],
            ],
        ];

        $response = ($this->updateController())($this->jsonRequest('PATCH', '/api/articles/1', $payload), 'articles', '1');

        self::assertSame(200, $response->getStatusCode());

        $document = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('Updated title', $document['data']['attributes']['title']);
    }

    public function testDeleteArticleRemovesResource(): void
    {
        $response = ($this->deleteController())('articles', '1');

        self::assertSame(204, $response->getStatusCode());

        self::assertNull($this->repository()->findOne('articles', '1', new Criteria()));
    }

    public function testPostTypeMismatchRaisesConflict(): void
    {
        $payload = [
            'data' => [
                'type' => 'authors',
                'attributes' => ['name' => 'Mismatch'],
            ],
        ];

        $this->expectException(ConflictException::class);
        ($this->createController())($this->jsonRequest('POST', '/api/articles', $payload), 'articles');
    }

    public function testPatchWithoutIdRaisesConflict(): void
    {
        $payload = [
            'data' => [
                'type' => 'articles',
                'attributes' => ['title' => 'Invalid'],
            ],
        ];

        $this->expectException(ConflictException::class);
        ($this->updateController())($this->jsonRequest('PATCH', '/api/articles/1', $payload), 'articles', '1');
    }

    public function testPatchMismatchedIdRaisesConflict(): void
    {
        $payload = [
            'data' => [
                'type' => 'articles',
                'id' => '2',
                'attributes' => ['title' => 'Invalid'],
            ],
        ];

        $this->expectException(ConflictException::class);
        ($this->updateController())($this->jsonRequest('PATCH', '/api/articles/1', $payload), 'articles', '1');
    }

    public function testPostClientIdWhenNotAllowedRaisesForbidden(): void
    {
        $payload = [
            'data' => [
                'type' => 'articles',
                'id' => 'custom',
                'attributes' => ['title' => 'Invalid'],
            ],
        ];

        $this->expectException(ForbiddenException::class);
        ($this->createController())($this->jsonRequest('POST', '/api/articles', $payload), 'articles');
    }

    public function testWritingUnknownAttributeRaisesBadRequest(): void
    {
        $payload = [
            'data' => [
                'type' => 'articles',
                'attributes' => ['unknown' => 'value'],
            ],
        ];

        $this->expectException(BadRequestException::class);
        ($this->createController())($this->jsonRequest('POST', '/api/articles', $payload), 'articles');
    }

    public function testWritingReadOnlyAttributeRaisesBadRequest(): void
    {
        $payload = [
            'data' => [
                'type' => 'articles',
                'attributes' => ['createdAt' => '2024-01-01T00:00:00Z'],
            ],
        ];

        $this->expectException(BadRequestException::class);
        ($this->createController())($this->jsonRequest('POST', '/api/articles', $payload), 'articles');
    }

    public function testRelationshipsInPayloadAreRejected(): void
    {
        $payload = [
            'data' => [
                'type' => 'articles',
                'attributes' => ['title' => 'With relationship'],
                'relationships' => ['author' => []],
            ],
        ];

        $this->expectException(BadRequestException::class);
        ($this->createController())($this->jsonRequest('POST', '/api/articles', $payload), 'articles');
    }

    public function testClientIdConflictProduces409(): void
    {
        $payload = [
            'data' => [
                'type' => 'authors',
                'id' => '1',
                'attributes' => ['name' => 'Duplicate'],
            ],
        ];

        $this->expectException(ConflictException::class);
        ($this->createController())($this->jsonRequest('POST', '/api/authors', $payload), 'authors');
    }

    public function testDeleteUnknownResourceRaises404(): void
    {
        $this->expectException(NotFoundException::class);
        ($this->deleteController())('articles', '999');
    }

    public function testUnsupportedMediaTypeResultsIn415(): void
    {
        $payload = json_encode(['data' => ['type' => 'articles', 'attributes' => ['title' => 'Invalid']]], JSON_THROW_ON_ERROR);
        $request = Request::create('/api/articles', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: $payload);

        $this->expectException(JsonApiHttpException::class);
        ($this->createController())($request, 'articles');
    }

    public function testArrayPayloadRaisesBadRequest(): void
    {
        $request = Request::create(
            '/api/articles',
            'POST',
            server: [
                'CONTENT_TYPE' => MediaType::JSON_API,
                'HTTP_ACCEPT' => MediaType::JSON_API,
            ],
            content: json_encode([['type' => 'articles']], JSON_THROW_ON_ERROR),
        );

        $this->expectException(BadRequestException::class);
        ($this->createController())($request, 'articles');
    }

    public function testArrayPayloadRaisesBadRequestOnPatch(): void
    {
        $request = Request::create(
            '/api/articles/1',
            'PATCH',
            server: [
                'CONTENT_TYPE' => MediaType::JSON_API,
                'HTTP_ACCEPT' => MediaType::JSON_API,
            ],
            content: json_encode([['type' => 'articles']], JSON_THROW_ON_ERROR),
        );

        $this->expectException(BadRequestException::class);
        ($this->updateController())($request, 'articles', '1');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonRequest(string $method, string $uri, array $payload): Request
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);

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
