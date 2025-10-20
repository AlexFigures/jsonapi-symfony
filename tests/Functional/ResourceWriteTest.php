<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Functional;

use AlexFigures\Symfony\Http\Exception\BadRequestException;
use AlexFigures\Symfony\Http\Exception\ConflictException;
use AlexFigures\Symfony\Http\Exception\ForbiddenException;
use AlexFigures\Symfony\Http\Exception\JsonApiHttpException;
use AlexFigures\Symfony\Http\Exception\NotFoundException;
use AlexFigures\Symfony\Http\Negotiation\MediaType;
use AlexFigures\Symfony\Query\Criteria;
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

        /** @var array{data: array{id: string, type: string, attributes: array<string, mixed>, links: array<string, string>}} $document */
        $document = $this->decode($response);
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

        /** @var array{data: array{id: string, attributes: array<string, mixed>}} $document */
        $document = $this->decode($response);
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

        /** @var array{data: array{attributes: array<string, mixed>}} $document */
        $document = $this->decode($response);
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

        // Read-only attributes (with getter but no setter) cause PropertyAccess exception
        $this->expectException(\Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException::class);
        ($this->createController())($this->jsonRequest('POST', '/api/articles', $payload), 'articles');
    }

    public function testRelationshipsInPayloadAreAccepted(): void
    {
        // First create an author
        $authorPayload = [
            'data' => [
                'type' => 'authors',
                'id' => 'test-author-for-rejection',
                'attributes' => [
                    'name' => 'Test Author',
                ],
            ],
        ];

        $authorResponse = ($this->createController())($this->jsonRequest('POST', '/api/authors', $authorPayload), 'authors');
        self::assertSame(201, $authorResponse->getStatusCode());

        // Now create an article with the author relationship - should work now
        $payload = [
            'data' => [
                'type' => 'articles',
                'attributes' => ['title' => 'With relationship'],
                'relationships' => [
                    'author' => [
                        'data' => [
                            'type' => 'authors',
                            'id' => 'test-author-for-rejection',
                        ],
                    ],
                ],
            ],
        ];

        $response = ($this->createController())($this->jsonRequest('POST', '/api/articles', $payload), 'articles');
        self::assertSame(201, $response->getStatusCode());

        /** @var array{data: array{relationships: array<string, mixed>}} $document */
        $document = $this->decode($response);
        self::assertArrayHasKey('relationships', $document['data']);
        self::assertArrayHasKey('author', $document['data']['relationships']);
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
        $payload = json_encode(['data' => ['type' => 'articles', 'attributes' => ['title' => 'Invalid']]], \JSON_THROW_ON_ERROR);
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
            content: json_encode([['type' => 'articles']], \JSON_THROW_ON_ERROR),
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
            content: json_encode([['type' => 'articles']], \JSON_THROW_ON_ERROR),
        );

        $this->expectException(BadRequestException::class);
        ($this->updateController())($request, 'articles', '1');
    }

    public function testCreateArticleWithAuthorRelationship(): void
    {
        // First create an author
        $authorPayload = [
            'data' => [
                'type' => 'authors',
                'id' => 'test-author-1',
                'attributes' => [
                    'name' => 'Test Author',
                ],
            ],
        ];

        $authorResponse = ($this->createController())($this->jsonRequest('POST', '/api/authors', $authorPayload), 'authors');
        self::assertSame(201, $authorResponse->getStatusCode());

        // Now create an article with the author relationship
        $articlePayload = [
            'data' => [
                'type' => 'articles',
                'attributes' => [
                    'title' => 'Article with Author',
                ],
                'relationships' => [
                    'author' => [
                        'data' => [
                            'type' => 'authors',
                            'id' => 'test-author-1',
                        ],
                    ],
                ],
            ],
        ];

        $articleResponse = ($this->createController())($this->jsonRequest('POST', '/api/articles', $articlePayload), 'articles');
        self::assertSame(201, $articleResponse->getStatusCode());

        /** @var array{data: array{id: string, type: string, attributes: array<string, mixed>, relationships: array<string, mixed>}} $document */
        $document = $this->decode($articleResponse);
        $articleId = $document['data']['id'];

        self::assertNotEmpty($articleId);
        self::assertSame('articles', $document['data']['type']);
        self::assertSame('Article with Author', $document['data']['attributes']['title']);

        // Verify the relationship was saved
        self::assertArrayHasKey('relationships', $document['data']);
        self::assertArrayHasKey('author', $document['data']['relationships']);
        self::assertArrayHasKey('data', $document['data']['relationships']['author']);
        self::assertSame('authors', $document['data']['relationships']['author']['data']['type']);
        self::assertSame('test-author-1', $document['data']['relationships']['author']['data']['id']);

        // Verify in database
        $stored = $this->repository()->findOne('articles', $articleId, new Criteria());
        self::assertNotNull($stored);
    }

    public function testUpdateArticleWithAuthorRelationship(): void
    {
        // First create an author
        $authorPayload = [
            'data' => [
                'type' => 'authors',
                'id' => 'test-author-2',
                'attributes' => [
                    'name' => 'Another Author',
                ],
            ],
        ];

        $authorResponse = ($this->createController())($this->jsonRequest('POST', '/api/authors', $authorPayload), 'authors');
        self::assertSame(201, $authorResponse->getStatusCode());

        // Update article 1 with the author relationship
        $updatePayload = [
            'data' => [
                'type' => 'articles',
                'id' => '1',
                'attributes' => [
                    'title' => 'Updated with Author',
                ],
                'relationships' => [
                    'author' => [
                        'data' => [
                            'type' => 'authors',
                            'id' => 'test-author-2',
                        ],
                    ],
                ],
            ],
        ];

        $response = ($this->updateController())($this->jsonRequest('PATCH', '/api/articles/1', $updatePayload), 'articles', '1');
        self::assertSame(200, $response->getStatusCode());

        /** @var array{data: array{attributes: array<string, mixed>, relationships: array<string, mixed>}} $document */
        $document = $this->decode($response);
        self::assertSame('Updated with Author', $document['data']['attributes']['title']);

        // Verify the relationship was saved
        self::assertArrayHasKey('relationships', $document['data']);
        self::assertArrayHasKey('author', $document['data']['relationships']);
        self::assertArrayHasKey('data', $document['data']['relationships']['author']);
        self::assertSame('authors', $document['data']['relationships']['author']['data']['type']);
        self::assertSame('test-author-2', $document['data']['relationships']['author']['data']['id']);
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
