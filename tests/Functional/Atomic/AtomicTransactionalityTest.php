<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Functional\Atomic;

use JsonApi\Symfony\Http\Negotiation\MediaType;
use JsonApi\Symfony\Http\Exception\BadRequestException;
use JsonApi\Symfony\Tests\Functional\JsonApiTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * GAP-001: Atomic Operations Transactionality
 *
 * Tests that atomic operations are truly transactional:
 * - All operations succeed or all fail
 * - Partial success is not committed
 * - Rollback restores original state
 */
final class AtomicTransactionalityTest extends JsonApiTestCase
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

    public function testFailureRollsBackAllOperations(): void
    {
        $controller = $this->atomicController();

        // Create initial state - one author
        $createAuthor = Request::create('/api/authors', 'POST', server: [
            'CONTENT_TYPE' => MediaType::JSON_API,
        ], content: json_encode([
            'data' => [
                'type' => 'authors',
                'attributes' => ['name' => 'Existing Author'],
            ],
        ], \JSON_THROW_ON_ERROR));

        $this->createController()($createAuthor, 'authors');

        // Verify initial state
        $initialCount = $this->repository()->count('authors');
        self::assertSame(1, $initialCount, 'Should have 1 author initially');

        // Atomic operations: 2 successful adds + 1 failing operation
        $payload = [
            'atomic:operations' => [
                [
                    'op' => 'add',
                    'ref' => ['type' => 'authors'],
                    'data' => [
                        'type' => 'authors',
                        'attributes' => ['name' => 'Author 1'],
                    ],
                ],
                [
                    'op' => 'add',
                    'ref' => ['type' => 'authors'],
                    'data' => [
                        'type' => 'authors',
                        'attributes' => ['name' => 'Author 2'],
                    ],
                ],
                [
                    'op' => 'add',
                    'ref' => ['type' => 'invalid-type'], // This will fail
                    'data' => [
                        'type' => 'invalid-type',
                        'attributes' => ['name' => 'Invalid'],
                    ],
                ],
            ],
        ];

        $request = Request::create('/api/operations', 'POST', server: [
            'CONTENT_TYPE' => MediaType::JSON_API_ATOMIC,
            'HTTP_ACCEPT' => MediaType::JSON_API_ATOMIC,
        ], content: json_encode($payload, \JSON_THROW_ON_ERROR));

        // Expect exception due to invalid type
        try {
            $controller($request);
            self::fail('Expected BadRequestException for invalid resource type');
        } catch (BadRequestException $e) {
            // Expected
        }

        // Verify rollback: count should still be 1 (no new authors created)
        $finalCount = $this->repository()->count('authors');
        self::assertSame(
            $initialCount,
            $finalCount,
            'All operations should be rolled back on failure. Expected 1 author, got ' . $finalCount
        );
    }

    public function testPartialSuccessNotCommitted(): void
    {
        $controller = $this->atomicController();

        // Verify initial state
        $initialArticleCount = $this->repository()->count('articles');
        $initialAuthorCount = $this->repository()->count('authors');

        // Atomic operations: create article, create author, then fail
        $payload = [
            'atomic:operations' => [
                [
                    'op' => 'add',
                    'ref' => ['type' => 'articles'],
                    'data' => [
                        'type' => 'articles',
                        'attributes' => [
                            'title' => 'Test Article',
                            'body' => 'Test Body',
                        ],
                    ],
                ],
                [
                    'op' => 'add',
                    'ref' => ['type' => 'authors'],
                    'data' => [
                        'type' => 'authors',
                        'attributes' => ['name' => 'Test Author'],
                    ],
                ],
                [
                    'op' => 'update',
                    'ref' => ['type' => 'articles', 'id' => 'non-existent-id'],
                    'data' => [
                        'type' => 'articles',
                        'id' => 'non-existent-id',
                        'attributes' => ['title' => 'Updated'],
                    ],
                ],
            ],
        ];

        $request = Request::create('/api/operations', 'POST', server: [
            'CONTENT_TYPE' => MediaType::JSON_API_ATOMIC,
            'HTTP_ACCEPT' => MediaType::JSON_API_ATOMIC,
        ], content: json_encode($payload, \JSON_THROW_ON_ERROR));

        // Expect exception due to non-existent resource
        try {
            $controller($request);
            self::fail('Expected exception for non-existent resource');
        } catch (\Throwable $e) {
            // Expected
        }

        // Verify nothing was committed
        $finalArticleCount = $this->repository()->count('articles');
        $finalAuthorCount = $this->repository()->count('authors');

        self::assertSame(
            $initialArticleCount,
            $finalArticleCount,
            'Article creation should be rolled back'
        );
        self::assertSame(
            $initialAuthorCount,
            $finalAuthorCount,
            'Author creation should be rolled back'
        );
    }

    public function testSuccessfulOperationsAreCommitted(): void
    {
        $controller = $this->atomicController();

        $initialCount = $this->repository()->count('authors');

        // All operations should succeed
        $payload = [
            'atomic:operations' => [
                [
                    'op' => 'add',
                    'ref' => ['type' => 'authors'],
                    'data' => [
                        'type' => 'authors',
                        'attributes' => ['name' => 'Author 1'],
                    ],
                ],
                [
                    'op' => 'add',
                    'ref' => ['type' => 'authors'],
                    'data' => [
                        'type' => 'authors',
                        'attributes' => ['name' => 'Author 2'],
                    ],
                ],
                [
                    'op' => 'add',
                    'ref' => ['type' => 'authors'],
                    'data' => [
                        'type' => 'authors',
                        'attributes' => ['name' => 'Author 3'],
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

        // Verify all operations were committed
        $finalCount = $this->repository()->count('authors');
        self::assertSame(
            $initialCount + 3,
            $finalCount,
            'All 3 authors should be created'
        );
    }

    public function testMixedOperationsRollbackOnFailure(): void
    {
        $controller = $this->atomicController();

        // Create initial author
        $createAuthor = Request::create('/api/authors', 'POST', server: [
            'CONTENT_TYPE' => MediaType::JSON_API,
        ], content: json_encode([
            'data' => [
                'type' => 'authors',
                'attributes' => ['name' => 'Original Author'],
            ],
        ], \JSON_THROW_ON_ERROR));

        $createResponse = $this->createController()($createAuthor, 'authors');
        $createdData = json_decode((string) $createResponse->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $authorId = $createdData['data']['id'];

        // Atomic operations: add, update, delete, then fail
        $payload = [
            'atomic:operations' => [
                [
                    'op' => 'add',
                    'ref' => ['type' => 'authors'],
                    'data' => [
                        'type' => 'authors',
                        'attributes' => ['name' => 'New Author'],
                    ],
                ],
                [
                    'op' => 'update',
                    'ref' => ['type' => 'authors', 'id' => $authorId],
                    'data' => [
                        'type' => 'authors',
                        'id' => $authorId,
                        'attributes' => ['name' => 'Updated Author'],
                    ],
                ],
                [
                    'op' => 'remove',
                    'ref' => ['type' => 'authors', 'id' => $authorId],
                ],
                [
                    'op' => 'add',
                    'ref' => ['type' => 'unknown-type'],
                    'data' => [
                        'type' => 'unknown-type',
                        'attributes' => ['name' => 'Fail'],
                    ],
                ],
            ],
        ];

        $request = Request::create('/api/operations', 'POST', server: [
            'CONTENT_TYPE' => MediaType::JSON_API_ATOMIC,
            'HTTP_ACCEPT' => MediaType::JSON_API_ATOMIC,
        ], content: json_encode($payload, \JSON_THROW_ON_ERROR));

        try {
            $controller($request);
            self::fail('Expected exception for unknown type');
        } catch (\Throwable $e) {
            // Expected
        }

        // Verify original author still exists with original name
        $author = $this->repository()->get('authors', $authorId);
        self::assertNotNull($author, 'Original author should still exist');
        self::assertSame('Original Author', $author->name, 'Author name should not be updated');

        // Verify no new author was created
        $count = $this->repository()->count('authors');
        self::assertSame(1, $count, 'Should still have only 1 author');
    }
}

