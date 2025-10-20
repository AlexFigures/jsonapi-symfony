<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Atomic;

use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Author;

/**
 * Integration tests for Atomic Operations transactionality with Doctrine.
 *
 * Tests:
 * - Failed operation rolls back all changes
 * - Multiple successful operations commit together
 * - Database constraint violations roll back all changes
 */
final class DoctrineAtomicTransactionalityTest extends DoctrineAtomicTestCase
{
    protected function getDatabaseUrl(): string
    {
        return $_ENV['DATABASE_URL_POSTGRES'] ?? 'postgresql://jsonapi:secret@postgres:5432/jsonapi_test?serverVersion=16&charset=utf8';
    }

    protected function getPlatform(): string
    {
        return 'postgresql';
    }

    /**
     * Test 1: Failed operation rolls back all previous successful operations.
     *
     * Note: In integration tests without Symfony exception listener,
     * exceptions are thrown directly. In production, JsonApiExceptionListener
     * would convert them to HTTP error responses.
     */
    public function testFailedOperationRollsBackAllChanges(): void
    {
        $initialCount = $this->getDatabaseResourceCount('authors');

        // 2 successful adds + 1 failing operation (update non-existent resource)
        $operations = [
            [
                'op' => 'add',
                'ref' => ['type' => 'authors'],
                'data' => [
                    'type' => 'authors',
                    'attributes' => [
                        'name' => 'Author 1',
                        'email' => 'author1@example.com',
                    ],
                ],
            ],
            [
                'op' => 'add',
                'ref' => ['type' => 'authors'],
                'data' => [
                    'type' => 'authors',
                    'attributes' => [
                        'name' => 'Author 2',
                        'email' => 'author2@example.com',
                    ],
                ],
            ],
            [
                'op' => 'update',
                'ref' => ['type' => 'authors', 'id' => 'non-existent-id-12345'],
                'data' => [
                    'type' => 'authors',
                    'id' => 'non-existent-id-12345',
                    'attributes' => [
                        'name' => 'Updated',
                    ],
                ],
            ],
        ];

        // Expect NotFoundException to be thrown (Doctrine rollback happens automatically)
        $this->expectException(\AlexFigures\Symfony\Http\Exception\NotFoundException::class);

        try {
            $this->executeAtomicRequest($operations);
        } finally {
            // Verify NO changes were committed (rollback happened)
            $this->em->clear();
            $finalCount = $this->getDatabaseResourceCount('authors');
            self::assertSame($initialCount, $finalCount, 'All operations should be rolled back');
        }
    }

    /**
     * Test 2: Multiple successful operations commit together atomically.
     */
    public function testMultipleSuccessfulOperationsCommitTogether(): void
    {
        $initialCount = $this->getDatabaseResourceCount('authors');

        $operations = [
            [
                'op' => 'add',
                'ref' => ['type' => 'authors'],
                'data' => [
                    'type' => 'authors',
                    'attributes' => [
                        'name' => 'Author 1',
                        'email' => 'author1@example.com',
                    ],
                ],
            ],
            [
                'op' => 'add',
                'ref' => ['type' => 'authors'],
                'data' => [
                    'type' => 'authors',
                    'attributes' => [
                        'name' => 'Author 2',
                        'email' => 'author2@example.com',
                    ],
                ],
            ],
            [
                'op' => 'add',
                'ref' => ['type' => 'authors'],
                'data' => [
                    'type' => 'authors',
                    'attributes' => [
                        'name' => 'Author 3',
                        'email' => 'author3@example.com',
                    ],
                ],
            ],
        ];

        $response = $this->executeAtomicRequest($operations);

        self::assertSame(200, $response->getStatusCode());

        // Verify all committed
        $this->em->clear();
        $finalCount = $this->getDatabaseResourceCount('authors');
        self::assertSame($initialCount + 3, $finalCount, 'All 3 authors should be committed');
    }

    /**
     * Test 3: Failed remove operation rolls back all previous operations.
     */
    public function testFailedRemoveRollsBackAllChanges(): void
    {
        $initialCount = $this->getDatabaseResourceCount('authors');

        // Create new author, then try to remove non-existent one
        $operations = [
            [
                'op' => 'add',
                'ref' => ['type' => 'authors'],
                'data' => [
                    'type' => 'authors',
                    'attributes' => [
                        'name' => 'New Author',
                        'email' => 'new@example.com',
                    ],
                ],
            ],
            [
                'op' => 'remove',
                'ref' => ['type' => 'authors', 'id' => 'non-existent-id-67890'],
            ],
        ];

        // Expect NotFoundException to be thrown
        $this->expectException(\AlexFigures\Symfony\Http\Exception\NotFoundException::class);

        try {
            $this->executeAtomicRequest($operations);
        } finally {
            // Verify the add was rolled back
            $this->em->clear();
            $finalCount = $this->getDatabaseResourceCount('authors');
            self::assertSame($initialCount, $finalCount, 'Add operation should be rolled back');
        }
    }

    /**
     * Test 4: Cascading operations (create, update, delete) in one transaction.
     */
    public function testCascadingOperationsCommitTogether(): void
    {
        // Create an author to update and delete
        $authorToUpdate = new Author();
        $authorToUpdate->setName('To Update');
        $authorToUpdate->setEmail('update@example.com');
        $this->em->persist($authorToUpdate);

        $authorToDelete = new Author();
        $authorToDelete->setName('To Delete');
        $authorToDelete->setEmail('delete@example.com');
        $this->em->persist($authorToDelete);

        $this->em->flush();

        $updateId = $authorToUpdate->getId();
        $deleteId = $authorToDelete->getId();
        $this->em->clear();

        $initialCount = $this->getDatabaseResourceCount('authors');

        // Create, update, delete in one transaction
        $operations = [
            [
                'op' => 'add',
                'ref' => ['type' => 'authors'],
                'data' => [
                    'type' => 'authors',
                    'attributes' => [
                        'name' => 'New Author',
                        'email' => 'new@example.com',
                    ],
                ],
            ],
            [
                'op' => 'update',
                'ref' => ['type' => 'authors', 'id' => $updateId],
                'data' => [
                    'type' => 'authors',
                    'id' => $updateId,
                    'attributes' => [
                        'name' => 'Updated Name',
                    ],
                ],
            ],
            [
                'op' => 'remove',
                'ref' => ['type' => 'authors', 'id' => $deleteId],
            ],
        ];

        $response = $this->executeAtomicRequest($operations);

        self::assertSame(200, $response->getStatusCode());

        // Verify: +1 created, -1 deleted = same count
        $this->em->clear();
        $finalCount = $this->getDatabaseResourceCount('authors');
        self::assertSame($initialCount, $finalCount);

        // Verify update happened
        $updated = $this->em->find(Author::class, $updateId);
        self::assertNotNull($updated);
        self::assertSame('Updated Name', $updated->getName());

        // Verify delete happened
        $this->assertDatabaseMissingResource('authors', $deleteId);
    }
}
