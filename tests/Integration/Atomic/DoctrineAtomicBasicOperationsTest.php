<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Atomic;

use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Author;

/**
 * Integration tests for basic Atomic Operations with Doctrine.
 *
 * Tests:
 * - Add operation commits to database
 * - Update operation commits to database
 * - Remove operation commits to database
 */
final class DoctrineAtomicBasicOperationsTest extends DoctrineAtomicTestCase
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
     * Test 1: Add operation creates resource in database.
     */
    public function testAddSingleResourceCommitsToDatabase(): void
    {
        $initialCount = $this->getDatabaseResourceCount('authors');

        $operations = [
            [
                'op' => 'add',
                'ref' => ['type' => 'authors'],
                'data' => [
                    'type' => 'authors',
                    'attributes' => [
                        'name' => 'John Doe',
                        'email' => 'john@example.com',
                    ],
                ],
            ],
        ];

        $response = $this->executeAtomicRequest($operations);

        self::assertSame(200, $response->getStatusCode());

        $decoded = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertArrayHasKey('atomic:results', $decoded);

        $results = $decoded['atomic:results'];
        self::assertIsArray($results);
        self::assertCount(1, $results);

        $result = $results[0];
        self::assertArrayHasKey('data', $result);
        self::assertSame('authors', $result['data']['type']);
        self::assertArrayHasKey('id', $result['data']);

        $createdId = $result['data']['id'];

        // Verify in database
        $this->assertDatabaseHasResource('authors', $createdId);

        // Verify count increased
        $finalCount = $this->getDatabaseResourceCount('authors');
        self::assertSame($initialCount + 1, $finalCount);

        // Verify attributes
        $author = $this->em->find(Author::class, $createdId);
        self::assertNotNull($author);
        self::assertSame('John Doe', $author->getName());
        self::assertSame('john@example.com', $author->getEmail());
    }

    /**
     * Test 2: Update operation modifies resource in database.
     */
    public function testUpdateResourceCommitsToDatabase(): void
    {
        // Create initial resource
        $author = new Author();
        $author->setName('Original Name');
        $author->setEmail('original@example.com');
        $this->em->persist($author);
        $this->em->flush();

        $authorId = $author->getId();
        $this->em->clear();

        // Update via atomic operation
        $operations = [
            [
                'op' => 'update',
                'ref' => ['type' => 'authors', 'id' => $authorId],
                'data' => [
                    'type' => 'authors',
                    'id' => $authorId,
                    'attributes' => [
                        'name' => 'Updated Name',
                    ],
                ],
            ],
        ];

        $response = $this->executeAtomicRequest($operations);

        self::assertSame(200, $response->getStatusCode());

        // Verify in database
        $this->em->clear();
        $updatedAuthor = $this->em->find(Author::class, $authorId);
        self::assertNotNull($updatedAuthor);
        self::assertSame('Updated Name', $updatedAuthor->getName());
        // Email should remain unchanged
        self::assertSame('original@example.com', $updatedAuthor->getEmail());
    }

    /**
     * Test 3: Remove operation deletes resource from database.
     */
    public function testRemoveResourceCommitsToDatabase(): void
    {
        // Create initial resource
        $author = new Author();
        $author->setName('To Be Deleted');
        $author->setEmail('delete@example.com');
        $this->em->persist($author);
        $this->em->flush();

        $authorId = $author->getId();
        $this->em->clear();

        $initialCount = $this->getDatabaseResourceCount('authors');

        // Remove via atomic operation
        $operations = [
            [
                'op' => 'remove',
                'ref' => ['type' => 'authors', 'id' => $authorId],
            ],
        ];

        $response = $this->executeAtomicRequest($operations);

        // Remove operations return 204 No Content
        self::assertSame(204, $response->getStatusCode());

        // Verify removed from database
        $this->em->clear();
        $this->assertDatabaseMissingResource('authors', $authorId);

        // Verify count decreased
        $finalCount = $this->getDatabaseResourceCount('authors');
        self::assertSame($initialCount - 1, $finalCount);
    }

    /**
     * Test 4: Add operation with client-provided ID.
     */
    public function testAddResourceWithClientProvidedId(): void
    {
        $clientId = 'client-provided-uuid-123';

        $operations = [
            [
                'op' => 'add',
                'ref' => ['type' => 'authors'],
                'data' => [
                    'type' => 'authors',
                    'id' => $clientId,
                    'attributes' => [
                        'name' => 'Client ID Author',
                        'email' => 'client@example.com',
                    ],
                ],
            ],
        ];

        $response = $this->executeAtomicRequest($operations);

        self::assertSame(200, $response->getStatusCode());

        // Verify in database with client-provided ID
        $this->assertDatabaseHasResource('authors', $clientId);

        $author = $this->em->find(Author::class, $clientId);
        self::assertNotNull($author);
        self::assertSame('Client ID Author', $author->getName());
    }

    /**
     * Test 5: Multiple add operations in sequence.
     */
    public function testMultipleAddOperationsCommitTogether(): void
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

        $decoded = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertCount(3, $decoded['atomic:results']);

        // Verify all created in database
        $finalCount = $this->getDatabaseResourceCount('authors');
        self::assertSame($initialCount + 3, $finalCount);

        // Verify each author exists
        foreach ($decoded['atomic:results'] as $result) {
            $id = $result['data']['id'];
            $this->assertDatabaseHasResource('authors', $id);
        }
    }

    /**
     * Test 6: Update operation with partial attributes.
     */
    public function testUpdateWithPartialAttributesPreservesOthers(): void
    {
        // Create initial resource
        $author = new Author();
        $author->setName('Original Name');
        $author->setEmail('original@example.com');
        $this->em->persist($author);
        $this->em->flush();

        $authorId = $author->getId();
        $this->em->clear();

        // Update only name, not email
        $operations = [
            [
                'op' => 'update',
                'ref' => ['type' => 'authors', 'id' => $authorId],
                'data' => [
                    'type' => 'authors',
                    'id' => $authorId,
                    'attributes' => [
                        'name' => 'New Name Only',
                    ],
                ],
            ],
        ];

        $response = $this->executeAtomicRequest($operations);

        self::assertSame(200, $response->getStatusCode());

        // Verify email preserved
        $this->em->clear();
        $updatedAuthor = $this->em->find(Author::class, $authorId);
        self::assertNotNull($updatedAuthor);
        self::assertSame('New Name Only', $updatedAuthor->getName());
        self::assertSame('original@example.com', $updatedAuthor->getEmail());
    }
}

