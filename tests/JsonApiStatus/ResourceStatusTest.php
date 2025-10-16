<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\JsonApiStatus;

use AlexFigures\Symfony\Http\Exception\NotFoundException;
use AlexFigures\Symfony\Http\Negotiation\MediaType;
use AlexFigures\Symfony\Tests\Functional\JsonApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

final class ResourceStatusTest extends JsonApiTestCase
{
    public function testGetMissingResourceReturns404(): void
    {
        $request = Request::create('/api/articles/' . Uuid::v4()->toRfc4122(), 'GET');

        $this->expectException(NotFoundException::class);
        ($this->resourceController())($request, 'articles', 'non-existent-id');
    }

    public function testRelationshipLinkageForEmptyRelationshipReturns200WithNullData(): void
    {
        // Create author without tags for deterministic state
        $authorPayload = [
            'data' => [
                'type' => 'authors',
                'id' => 'status-author',
                'attributes' => [
                    'name' => 'Status Author',
                ],
            ],
        ];

        ($this->createController())($this->jsonRequest('POST', '/api/authors', $authorPayload), 'authors');

        $articlePayload = [
            'data' => [
                'type' => 'articles',
                'attributes' => [
                    'title' => 'Relationship Status Probe',
                ],
                'relationships' => [
                    'author' => [
                        'data' => [
                            'type' => 'authors',
                            'id' => 'status-author',
                        ],
                    ],
                ],
            ],
        ];

        $response = ($this->createController())($this->jsonRequest('POST', '/api/articles', $articlePayload), 'articles');
        $document = $this->decode($response);
        $articleId = $document['data']['id'];

        $request = Request::create("/api/articles/{$articleId}/relationships/tags", 'GET');
        $response = ($this->relationshipGetController())($request, 'articles', $articleId, 'tags');

        $document = $this->decode($response);

        self::assertSame([], $document['data'], 'Expected empty linkage array for to-many relationship with no members.');
    }

    public function testUnknownRelationshipReturns404(): void
    {
        $request = Request::create('/api/articles/1/relationships/comments', 'GET');

        $this->expectException(NotFoundException::class);
        ($this->relationshipGetController())($request, 'articles', '1', 'comments');
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
