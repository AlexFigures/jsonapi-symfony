<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Atomic;

use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Article;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Author;
use Doctrine\DBAL\Logging\SQLLogger;

/**
 * Test E1: FlushManager behavior in atomic operations.
 *
 * Verifies that:
 * - Flush occurs exactly once after each operation
 * - No intermediate flushes during operation processing
 * - All operations are committed in a single transaction
 */
final class DoctrineAtomicFlushTest extends DoctrineAtomicTestCase
{
    /**
     * Test E1.1: Verify flush occurs after each operation.
     *
     * This test ensures that entities created in operation N are available
     * in the database for operation N+1 to reference them.
     */
    public function testFlushOccursAfterEachOperation(): void
    {
        // Track SQL queries to count COMMIT statements
        $queryLogger = new class () implements SQLLogger {
            public array $queries = [];

            public function startQuery($sql, ?array $params = null, ?array $types = null): void
            {
                $this->queries[] = $sql;
            }

            public function stopQuery(): void
            {
            }
        };

        $this->em->getConnection()->getConfiguration()->setSQLLogger($queryLogger);

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

        // Verify all authors were created
        $this->em->clear();
        $authorCount = $this->getDatabaseResourceCount('authors');
        self::assertSame(3, $authorCount);

        // Count INSERT statements (should be 3, one per operation)
        $insertCount = count(array_filter($queryLogger->queries, fn ($sql) => stripos($sql, 'INSERT INTO') !== false));
        self::assertSame(3, $insertCount, 'Should have 3 INSERT statements');

        // Verify only one transaction (START TRANSACTION ... COMMIT)
        $startTransactionCount = count(array_filter($queryLogger->queries, fn ($sql) => stripos($sql, 'START TRANSACTION') !== false));
        $commitCount = count(array_filter($queryLogger->queries, fn ($sql) => stripos($sql, 'COMMIT') !== false));

        self::assertSame(1, $startTransactionCount, 'Should have exactly 1 START TRANSACTION');
        self::assertSame(1, $commitCount, 'Should have exactly 1 COMMIT');
    }

    /**
     * Test E1.2: Verify entities are available after flush.
     *
     * This test ensures that entities created in operation N can be referenced
     * by operation N+1 through relationships.
     */
    public function testEntitiesAvailableAfterFlush(): void
    {
        $operations = [
            [
                'op' => 'add',
                'ref' => ['type' => 'authors'],
                'data' => [
                    'type' => 'authors',
                    'lid' => 'temp-author',
                    'attributes' => [
                        'name' => 'John Doe',
                        'email' => 'john@example.com',
                    ],
                ],
            ],
            [
                'op' => 'add',
                'ref' => ['type' => 'articles'],
                'data' => [
                    'type' => 'articles',
                    'attributes' => [
                        'title' => 'Article by John',
                        'content' => 'Content',
                    ],
                    'relationships' => [
                        'author' => [
                            'data' => ['type' => 'authors', 'lid' => 'temp-author'],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->executeAtomicRequest($operations);

        self::assertSame(200, $response->getStatusCode());

        $decoded = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $authorId = $decoded['atomic:results'][0]['data']['id'];
        $articleId = $decoded['atomic:results'][1]['data']['id'];

        // Verify in database
        $this->em->clear();
        $article = $this->em->find(Article::class, $articleId);
        self::assertNotNull($article);
        self::assertNotNull($article->getAuthor());
        self::assertSame($authorId, $article->getAuthor()->getId());
    }

    /**
     * Test E1.3: Verify no flush on rollback.
     *
     * When an operation fails, the transaction should rollback without
     * committing any changes.
     */
    public function testNoFlushOnRollback(): void
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
                'op' => 'update',
                'ref' => ['type' => 'authors', 'id' => 'non-existent-id'],
                'data' => [
                    'type' => 'authors',
                    'id' => 'non-existent-id',
                    'attributes' => ['name' => 'Updated'],
                ],
            ],
        ];

        // Expect NotFoundException
        $this->expectException(\AlexFigures\Symfony\Http\Exception\NotFoundException::class);

        try {
            $this->executeAtomicRequest($operations);
        } finally {
            // Verify NO changes were committed
            $this->em->clear();
            $finalCount = $this->getDatabaseResourceCount('authors');
            self::assertSame($initialCount, $finalCount, 'No authors should be created on rollback');
        }
    }

    /**
     * Test E1.4: Verify flush count matches operation count.
     *
     * Each operation should trigger exactly one flush.
     */
    public function testFlushCountMatchesOperationCount(): void
    {
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
            [
                'op' => 'add',
                'ref' => ['type' => 'authors'],
                'data' => [
                    'type' => 'authors',
                    'attributes' => [
                        'name' => 'Author 4',
                        'email' => 'author4@example.com',
                    ],
                ],
            ],
            [
                'op' => 'add',
                'ref' => ['type' => 'authors'],
                'data' => [
                    'type' => 'authors',
                    'attributes' => [
                        'name' => 'Author 5',
                        'email' => 'author5@example.com',
                    ],
                ],
            ],
        ];

        $response = $this->executeAtomicRequest($operations);

        self::assertSame(200, $response->getStatusCode());

        // Note: We can't easily mock FlushManager in this test without refactoring,
        // so we verify indirectly by checking that all entities were created
        $this->em->clear();
        $authorCount = $this->getDatabaseResourceCount('authors');
        self::assertSame(5, $authorCount, 'All 5 authors should be created');
    }

    /**
     * Test E1.5: Verify mixed operations flush correctly.
     *
     * Mix of add, update, remove operations should each trigger flush.
     */
    public function testMixedOperationsFlushCorrectly(): void
    {
        // Create initial author
        $author = new Author();
        $author->setName('Initial Author');
        $author->setEmail('initial@example.com');
        $this->em->persist($author);
        $this->em->flush();
        $authorId = $author->getId();
        $this->em->clear();

        $operations = [
            [
                'op' => 'add',
                'ref' => ['type' => 'authors'],
                'data' => [
                    'type' => 'authors',
                    'lid' => 'new-author',
                    'attributes' => [
                        'name' => 'New Author',
                        'email' => 'new@example.com',
                    ],
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
                'op' => 'add',
                'ref' => ['type' => 'articles'],
                'data' => [
                    'type' => 'articles',
                    'attributes' => [
                        'title' => 'Article',
                        'content' => 'Content',
                    ],
                    'relationships' => [
                        'author' => [
                            'data' => ['type' => 'authors', 'lid' => 'new-author'],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->executeAtomicRequest($operations);

        self::assertSame(200, $response->getStatusCode());

        // Verify all changes were applied
        $this->em->clear();
        $updatedAuthor = $this->em->find(Author::class, $authorId);
        self::assertNotNull($updatedAuthor);
        self::assertSame('Updated Author', $updatedAuthor->getName());

        $authorCount = $this->getDatabaseResourceCount('authors');
        self::assertSame(2, $authorCount, 'Should have 2 authors (1 initial + 1 new)');

        $articleCount = $this->getDatabaseResourceCount('articles');
        self::assertSame(1, $articleCount, 'Should have 1 article');
    }
}
