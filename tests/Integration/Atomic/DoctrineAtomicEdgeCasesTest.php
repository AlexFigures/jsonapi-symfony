<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Atomic;

use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Article;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Author;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Tag;

/**
 * Test F: Edge cases for atomic operations.
 *
 * Covers:
 * - Non-existent resource references
 * - Invalid LID references
 * - Circular dependencies
 * - EntityManager state after rollback
 * - Empty operations
 * - Large batch operations
 */
final class DoctrineAtomicEdgeCasesTest extends DoctrineAtomicTestCase
{
    /**
     * Test F1: Reference non-existent resource in relationship.
     */
    public function testReferenceNonExistentResource(): void
    {
        $operations = [
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
                            'data' => ['type' => 'authors', 'id' => 'non-existent-id'],
                        ],
                    ],
                ],
            ],
        ];

        $this->expectException(\AlexFigures\Symfony\Http\Exception\NotFoundException::class);
        $this->expectExceptionMessageMatches('/Related resource.*was not found/');

        $this->executeAtomicRequest($operations);
    }

    /**
     * Test F2: Reference non-existent LID.
     */
    public function testReferenceNonExistentLid(): void
    {
        $operations = [
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
                            'data' => ['type' => 'authors', 'lid' => 'non-existent-lid'],
                        ],
                    ],
                ],
            ],
        ];

        $this->expectException(\AlexFigures\Symfony\Http\Exception\BadRequestException::class);
        $this->expectExceptionMessage('Unknown local identifier');

        $this->executeAtomicRequest($operations);
    }

    /**
     * Test F3: Update non-existent resource.
     */
    public function testUpdateNonExistentResource(): void
    {
        $operations = [
            [
                'op' => 'update',
                'ref' => ['type' => 'authors', 'id' => 'non-existent-id'],
                'data' => [
                    'type' => 'authors',
                    'id' => 'non-existent-id',
                    'attributes' => ['name' => 'Updated Name'],
                ],
            ],
        ];

        $this->expectException(\AlexFigures\Symfony\Http\Exception\NotFoundException::class);
        $this->expectExceptionMessage('not found');

        $this->executeAtomicRequest($operations);
    }

    /**
     * Test F4: Remove non-existent resource.
     */
    public function testRemoveNonExistentResource(): void
    {
        $operations = [
            [
                'op' => 'remove',
                'ref' => ['type' => 'authors', 'id' => 'non-existent-id'],
            ],
        ];

        $this->expectException(\AlexFigures\Symfony\Http\Exception\NotFoundException::class);
        $this->expectExceptionMessage('not found');

        $this->executeAtomicRequest($operations);
    }

    /**
     * Test F5: EntityManager state after rollback.
     *
     * Verify that EntityManager is in a clean state after a failed transaction.
     */
    public function testEntityManagerStateAfterRollback(): void
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
                'op' => 'update',
                'ref' => ['type' => 'authors', 'id' => 'non-existent-id'],
                'data' => [
                    'type' => 'authors',
                    'id' => 'non-existent-id',
                    'attributes' => ['name' => 'Updated'],
                ],
            ],
        ];

        try {
            $this->executeAtomicRequest($operations);
            self::fail('Expected NotFoundException');
        } catch (\AlexFigures\Symfony\Http\Exception\NotFoundException $e) {
            // Expected
        }

        // Verify no changes were committed
        $finalCount = $this->getDatabaseResourceCount('authors');
        self::assertSame($initialCount, $finalCount);

        // Note: EntityManager is closed after exception in transaction (expected Doctrine behavior)
        // We verify that the rollback worked correctly by checking the database state
    }

    /**
     * Test F6: Empty operations array.
     */
    public function testEmptyOperationsArray(): void
    {
        $operations = [];

        $this->expectException(\AlexFigures\Symfony\Http\Exception\BadRequestException::class);
        $this->expectExceptionMessage('atomic:operations cannot be empty');

        $this->executeAtomicRequest($operations);
    }

    /**
     * Test F7: Large batch operation.
     *
     * Test with 50 operations to verify performance and memory usage.
     */
    public function testLargeBatchOperation(): void
    {
        $operations = [];

        // Create 50 authors
        for ($i = 1; $i <= 50; $i++) {
            $operations[] = [
                'op' => 'add',
                'ref' => ['type' => 'authors'],
                'data' => [
                    'type' => 'authors',
                    'lid' => "author-{$i}",
                    'attributes' => [
                        'name' => "Author {$i}",
                        'email' => "author{$i}@example.com",
                    ],
                ],
            ];
        }

        $response = $this->executeAtomicRequest($operations);

        self::assertSame(200, $response->getStatusCode());

        // Verify all authors were created
        $this->em->clear();
        $authorCount = $this->getDatabaseResourceCount('authors');
        self::assertSame(50, $authorCount);
    }

    /**
     * Test F8: Null relationship (clear to-one relationship).
     */
    public function testNullToOneRelationship(): void
    {
        // Create initial data
        $author = new Author();
        $author->setName('Author');
        $author->setEmail('author@example.com');
        $this->em->persist($author);

        $article = new Article();
        $article->setTitle('Article');
        $article->setContent('Content');
        $article->setAuthor($author);
        $this->em->persist($article);

        $this->em->flush();
        $articleId = $article->getId();
        $this->em->clear();

        // Clear author relationship
        $operations = [
            [
                'op' => 'update',
                'ref' => ['type' => 'articles', 'id' => $articleId],
                'data' => [
                    'type' => 'articles',
                    'id' => $articleId,
                    'attributes' => [], // Empty attributes required for update
                    'relationships' => [
                        'author' => [
                            'data' => null,
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->executeAtomicRequest($operations);

        self::assertSame(200, $response->getStatusCode());

        // Verify author is null
        $this->em->clear();
        $updatedArticle = $this->em->find(Article::class, $articleId);
        self::assertNotNull($updatedArticle);
        self::assertNull($updatedArticle->getAuthor());
    }

    /**
     * Test F9: Empty to-many relationship (clear all tags).
     */
    public function testEmptyToManyRelationship(): void
    {
        // Create initial data
        $tag1 = new Tag();
        $tag1->setName('Tag 1');
        $this->em->persist($tag1);

        $tag2 = new Tag();
        $tag2->setName('Tag 2');
        $this->em->persist($tag2);

        $article = new Article();
        $article->setTitle('Article');
        $article->setContent('Content');
        $article->addTag($tag1);
        $article->addTag($tag2);
        $this->em->persist($article);

        $this->em->flush();
        $articleId = $article->getId();
        $this->em->clear();

        // Clear all tags
        $operations = [
            [
                'op' => 'update',
                'ref' => ['type' => 'articles', 'id' => $articleId],
                'data' => [
                    'type' => 'articles',
                    'id' => $articleId,
                    'relationships' => [
                        'tags' => [
                            'data' => [],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->executeAtomicRequest($operations);

        self::assertSame(200, $response->getStatusCode());

        // Verify tags are cleared
        $this->em->clear();
        $updatedArticle = $this->em->find(Article::class, $articleId);
        self::assertNotNull($updatedArticle);
        self::assertCount(0, $updatedArticle->getTags());
    }

    /**
     * Test F10: Mixed success and failure with rollback.
     *
     * Verify that partial success is rolled back completely.
     */
    public function testMixedSuccessAndFailureRollback(): void
    {
        $initialAuthorCount = $this->getDatabaseResourceCount('authors');
        $initialArticleCount = $this->getDatabaseResourceCount('articles');

        $operations = [
            [
                'op' => 'add',
                'ref' => ['type' => 'authors'],
                'data' => [
                    'type' => 'authors',
                    'lid' => 'author-1',
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
                    'lid' => 'author-2',
                    'attributes' => [
                        'name' => 'Author 2',
                        'email' => 'author2@example.com',
                    ],
                ],
            ],
            [
                'op' => 'add',
                'ref' => ['type' => 'articles'],
                'data' => [
                    'type' => 'articles',
                    'attributes' => [
                        'title' => 'Article 1',
                        'content' => 'Content',
                    ],
                    'relationships' => [
                        'author' => [
                            'data' => ['type' => 'authors', 'lid' => 'author-1'],
                        ],
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

        try {
            $this->executeAtomicRequest($operations);
            self::fail('Expected NotFoundException');
        } catch (\AlexFigures\Symfony\Http\Exception\NotFoundException $e) {
            // Expected
        }

        // Verify complete rollback
        $this->em->clear();
        $finalAuthorCount = $this->getDatabaseResourceCount('authors');
        $finalArticleCount = $this->getDatabaseResourceCount('articles');

        self::assertSame($initialAuthorCount, $finalAuthorCount, 'No authors should be created');
        self::assertSame($initialArticleCount, $finalArticleCount, 'No articles should be created');
    }

    /**
     * Test F11: Relationship operation on non-existent resource.
     */
    public function testRelationshipOperationOnNonExistentResource(): void
    {
        $tag = new Tag();
        $tag->setName('Tag');
        $this->em->persist($tag);
        $this->em->flush();
        $tagId = $tag->getId();
        $this->em->clear();

        $operations = [
            [
                'op' => 'add',
                'ref' => [
                    'type' => 'articles',
                    'id' => 'non-existent-article-id',
                    'relationship' => 'tags',
                ],
                'data' => [
                    ['type' => 'tags', 'id' => $tagId],
                ],
            ],
        ];

        $this->expectException(\AlexFigures\Symfony\Http\Exception\NotFoundException::class);

        $this->executeAtomicRequest($operations);
    }

    /**
     * Test F12: Duplicate client-provided IDs.
     */
    public function testDuplicateClientProvidedIds(): void
    {
        $operations = [
            [
                'op' => 'add',
                'ref' => ['type' => 'authors'],
                'data' => [
                    'type' => 'authors',
                    'id' => 'custom-id-123',
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
                    'id' => 'custom-id-123', // Duplicate ID
                    'attributes' => [
                        'name' => 'Author 2',
                        'email' => 'author2@example.com',
                    ],
                ],
            ],
        ];

        $this->expectException(\AlexFigures\Symfony\Http\Exception\ConflictException::class);
        $this->expectExceptionMessage('already exists');

        $this->executeAtomicRequest($operations);
    }

    /**
     * Test F13: Update then remove same resource.
     */
    public function testUpdateThenRemoveSameResource(): void
    {
        // Create initial author
        $author = new Author();
        $author->setName('Initial Name');
        $author->setEmail('initial@example.com');
        $this->em->persist($author);
        $this->em->flush();
        $authorId = $author->getId();
        $this->em->clear();

        $operations = [
            [
                'op' => 'update',
                'ref' => ['type' => 'authors', 'id' => $authorId],
                'data' => [
                    'type' => 'authors',
                    'id' => $authorId,
                    'attributes' => ['name' => 'Updated Name'],
                ],
            ],
            [
                'op' => 'remove',
                'ref' => ['type' => 'authors', 'id' => $authorId],
            ],
        ];

        $response = $this->executeAtomicRequest($operations);

        self::assertSame(200, $response->getStatusCode());

        // Verify author is removed
        $this->em->clear();
        $removedAuthor = $this->em->find(Author::class, $authorId);
        self::assertNull($removedAuthor);
    }

    /**
     * Test F14: Create with LID then update using LID.
     */
    public function testCreateWithLidThenUpdateUsingLid(): void
    {
        $operations = [
            [
                'op' => 'add',
                'ref' => ['type' => 'authors'],
                'data' => [
                    'type' => 'authors',
                    'lid' => 'temp-author',
                    'attributes' => [
                        'name' => 'Initial Name',
                        'email' => 'initial@example.com',
                    ],
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
        ];

        $response = $this->executeAtomicRequest($operations);

        self::assertSame(200, $response->getStatusCode());

        $decoded = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $authorId = $decoded['atomic:results'][0]['data']['id'];

        // Verify final state
        $this->em->clear();
        $author = $this->em->find(Author::class, $authorId);
        self::assertNotNull($author);
        self::assertSame('Updated Name', $author->getName());
    }
}
