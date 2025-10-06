<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Functional\Atomic;

use JsonApi\Symfony\Http\Negotiation\MediaType;
use JsonApi\Symfony\Http\Exception\BadRequestException;
use JsonApi\Symfony\Tests\Functional\JsonApiTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * GAP-002: LID (Local ID) Resolution
 *
 * Tests that Local IDs (lid) work correctly in atomic operations:
 * - LID from first operation can be referenced in subsequent operations
 * - LID works in relationships
 * - Duplicate LID throws error
 * - LID is resolved to actual ID in results
 */
final class LidResolutionTest extends JsonApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clear seeded data to have clean state
        $this->clearRepository();
    }

    private function clearRepository(): void
    {
        $repo = $this->repository();
        $reflection = new \ReflectionClass($repo);
        $property = $reflection->getProperty('data');
        $property->setAccessible(true);
        $property->setValue($repo, []);
    }

    public function testLidResolutionInSubsequentOperations(): void
    {
        $controller = $this->atomicController();

        // Create author with lid, then update it using lid reference
        $payload = [
            'atomic:operations' => [
                [
                    'op' => 'add',
                    'ref' => ['type' => 'authors'],
                    'data' => [
                        'type' => 'authors',
                        'lid' => 'temp-author-1',
                        'attributes' => ['name' => 'Original Name'],
                    ],
                ],
                [
                    'op' => 'update',
                    'ref' => ['type' => 'authors', 'lid' => 'temp-author-1'],
                    'data' => [
                        'type' => 'authors',
                        'lid' => 'temp-author-1',
                        'attributes' => ['name' => 'Updated Name'],
                    ],
                ],
            ],
        ];

        $request = Request::create('/api/operations', 'POST', server: [
            'CONTENT_TYPE' => MediaType::JSON_API_ATOMIC,
            'HTTP_ACCEPT' => MediaType::JSON_API_ATOMIC,
        ], content: json_encode($payload, \JSON_THROW_ON_ERROR));

        $response = $controller($request);

        self::assertSame(200, $response->getStatusCode());

        $decoded = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertArrayHasKey('atomic:results', $decoded);

        $results = $decoded['atomic:results'];
        self::assertIsArray($results);
        self::assertCount(2, $results);

        // First result: author with actual ID (not lid)
        $authorResult = $results[0];
        self::assertArrayHasKey('data', $authorResult);
        self::assertSame('authors', $authorResult['data']['type']);
        self::assertArrayHasKey('id', $authorResult['data']);
        self::assertNotEquals('temp-author-1', $authorResult['data']['id'], 'LID should be resolved to actual ID');

        // Second result: updated author with same ID
        $updateResult = $results[1];
        self::assertArrayHasKey('data', $updateResult);
        self::assertSame($authorResult['data']['id'], $updateResult['data']['id'], 'Update should reference same author');

        // Verify the author was updated
        $authorId = $authorResult['data']['id'];
        $author = $this->repository()->get('authors', $authorId);
        self::assertNotNull($author);
        self::assertSame('Updated Name', $author->name);
    }

    public function testLidInRelationships(): void
    {
        $controller = $this->atomicController();

        // Create author and tag with lids, then use them in relationship operation
        $payload = [
            'atomic:operations' => [
                [
                    'op' => 'add',
                    'ref' => ['type' => 'authors'],
                    'data' => [
                        'type' => 'authors',
                        'lid' => 'temp-author',
                        'attributes' => ['name' => 'Test Author'],
                    ],
                ],
                [
                    'op' => 'add',
                    'ref' => ['type' => 'tags'],
                    'data' => [
                        'type' => 'tags',
                        'lid' => 'temp-tag',
                        'attributes' => ['name' => 'php'],
                    ],
                ],
                [
                    'op' => 'add',
                    'ref' => ['type' => 'articles'],
                    'data' => [
                        'type' => 'articles',
                        'lid' => 'temp-article',
                        'attributes' => ['title' => 'Test Article'],
                    ],
                ],
                [
                    'op' => 'update',
                    'ref' => ['type' => 'articles', 'lid' => 'temp-article', 'relationship' => 'author'],
                    'data' => [
                        'type' => 'authors',
                        'lid' => 'temp-author', // Reference LID in relationship
                    ],
                ],
                [
                    'op' => 'add',
                    'ref' => ['type' => 'articles', 'lid' => 'temp-article', 'relationship' => 'tags'],
                    'data' => [
                        [
                            'type' => 'tags',
                            'lid' => 'temp-tag', // Reference LID in to-many relationship
                        ],
                    ],
                ],
            ],
        ];

        $request = Request::create('/api/operations', 'POST', server: [
            'CONTENT_TYPE' => MediaType::JSON_API_ATOMIC,
            'HTTP_ACCEPT' => MediaType::JSON_API_ATOMIC,
        ], content: json_encode($payload, \JSON_THROW_ON_ERROR));

        $response = $controller($request);

        self::assertSame(200, $response->getStatusCode());

        $decoded = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertArrayHasKey('atomic:results', $decoded);

        $results = $decoded['atomic:results'];
        self::assertCount(5, $results);

        // Verify all resources were created
        $authorId = $results[0]['data']['id'];
        $tagId = $results[1]['data']['id'];
        $articleId = $results[2]['data']['id'];

        $article = $this->repository()->get('articles', $articleId);
        self::assertNotNull($article);
        self::assertSame($authorId, $article->getAuthor()->id, 'Article should reference the created author via LID');
        self::assertCount(1, $article->getTags(), 'Article should have 1 tag added via LID');
        self::assertSame($tagId, $article->getTags()[0]->id);
    }

    public function testDuplicateLidThrowsError(): void
    {
        $controller = $this->atomicController();

        // Try to use the same lid twice
        $payload = [
            'atomic:operations' => [
                [
                    'op' => 'add',
                    'ref' => ['type' => 'authors'],
                    'data' => [
                        'type' => 'authors',
                        'lid' => 'duplicate-lid',
                        'attributes' => ['name' => 'Author 1'],
                    ],
                ],
                [
                    'op' => 'add',
                    'ref' => ['type' => 'authors'],
                    'data' => [
                        'type' => 'authors',
                        'lid' => 'duplicate-lid', // Same lid as first operation
                        'attributes' => ['name' => 'Author 2'],
                    ],
                ],
            ],
        ];

        $request = Request::create('/api/operations', 'POST', server: [
            'CONTENT_TYPE' => MediaType::JSON_API_ATOMIC,
            'HTTP_ACCEPT' => MediaType::JSON_API_ATOMIC,
        ], content: json_encode($payload, \JSON_THROW_ON_ERROR));

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessageMatches('/duplicate|lid/i');

        $controller($request);
    }

    public function testLidResolvedInUpdateOperation(): void
    {
        $controller = $this->atomicController();

        // Create author with lid, then update it using the same lid
        $payload = [
            'atomic:operations' => [
                [
                    'op' => 'add',
                    'ref' => ['type' => 'authors'],
                    'data' => [
                        'type' => 'authors',
                        'lid' => 'temp-author',
                        'attributes' => ['name' => 'Original Name'],
                    ],
                ],
                [
                    'op' => 'update',
                    'ref' => ['type' => 'authors', 'lid' => 'temp-author'],
                    'data' => [
                        'type' => 'authors',
                        'lid' => 'temp-author',
                        'attributes' => ['name' => 'Updated Name'],
                    ],
                ],
            ],
        ];

        $request = Request::create('/api/operations', 'POST', server: [
            'CONTENT_TYPE' => MediaType::JSON_API_ATOMIC,
            'HTTP_ACCEPT' => MediaType::JSON_API_ATOMIC,
        ], content: json_encode($payload, \JSON_THROW_ON_ERROR));

        $response = $controller($request);

        self::assertSame(200, $response->getStatusCode());

        $decoded = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        $results = $decoded['atomic:results'];
        self::assertCount(2, $results);

        // Both results should have the same actual ID
        $authorId = $results[0]['data']['id'];
        self::assertSame($authorId, $results[1]['data']['id']);

        // Verify the author was updated
        $author = $this->repository()->get('authors', $authorId);
        self::assertNotNull($author);
        self::assertSame('Updated Name', $author->name);
    }
}

