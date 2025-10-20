<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Atomic;

use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Article;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Author;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Tag;

/**
 * Integration tests for Atomic Operations with Doctrine - Local IDs (Phase 2).
 *
 * Tests Local ID (lid) resolution with real PostgreSQL database:
 * - LID registration and resolution
 * - LID usage in subsequent operations
 * - LID usage in relationships
 * - LID in to-one and to-many relationships
 */
final class DoctrineAtomicLocalIdsTest extends DoctrineAtomicTestCase
{
    /**
     * Test D1: Create resource with lid and reference it in update operation.
     */
    public function testLidResolutionInSubsequentOperation(): void
    {
        $operations = [
            [
                'op' => 'add',
                'ref' => ['type' => 'authors'],
                'data' => [
                    'type' => 'authors',
                    'lid' => 'temp-author-1',
                    'attributes' => [
                        'name' => 'Original Name',
                        'email' => 'original@example.com',
                    ],
                ],
            ],
            [
                'op' => 'update',
                'ref' => ['type' => 'authors', 'lid' => 'temp-author-1'],
                'data' => [
                    'type' => 'authors',
                    'lid' => 'temp-author-1',
                    'attributes' => [
                        'name' => 'Updated Name',
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
        self::assertCount(2, $results);

        // Both operations should reference the same resource
        $authorId = $results[0]['data']['id'];
        self::assertSame($authorId, $results[1]['data']['id']);

        // Verify in database
        $this->em->clear();
        $author = $this->em->find(Author::class, $authorId);
        self::assertNotNull($author);
        self::assertSame('Updated Name', $author->getName());
    }

    /**
     * Test D2: Use lid in to-one relationship.
     */
    public function testLidInToOneRelationship(): void
    {
        $operations = [
            [
                'op' => 'add',
                'ref' => ['type' => 'authors'],
                'data' => [
                    'type' => 'authors',
                    'lid' => 'temp-author',
                    'attributes' => [
                        'name' => 'Test Author',
                        'email' => 'test@example.com',
                    ],
                ],
            ],
            [
                'op' => 'add',
                'ref' => ['type' => 'articles'],
                'data' => [
                    'type' => 'articles',
                    'attributes' => [
                        'title' => 'Test Article',
                        'content' => 'Article content',
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
     * Test D3: Use lid in to-many relationship.
     */
    public function testLidInToManyRelationship(): void
    {
        $operations = [
            [
                'op' => 'add',
                'ref' => ['type' => 'tags'],
                'data' => [
                    'type' => 'tags',
                    'lid' => 'temp-tag-1',
                    'attributes' => ['name' => 'PHP'],
                ],
            ],
            [
                'op' => 'add',
                'ref' => ['type' => 'tags'],
                'data' => [
                    'type' => 'tags',
                    'lid' => 'temp-tag-2',
                    'attributes' => ['name' => 'Symfony'],
                ],
            ],
            [
                'op' => 'add',
                'ref' => ['type' => 'articles'],
                'data' => [
                    'type' => 'articles',
                    'attributes' => [
                        'title' => 'PHP Article',
                        'content' => 'Content',
                    ],
                    'relationships' => [
                        'tags' => [
                            'data' => [
                                ['type' => 'tags', 'lid' => 'temp-tag-1'],
                                ['type' => 'tags', 'lid' => 'temp-tag-2'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->executeAtomicRequest($operations);

        self::assertSame(200, $response->getStatusCode());

        $decoded = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $tag1Id = $decoded['atomic:results'][0]['data']['id'];
        $tag2Id = $decoded['atomic:results'][1]['data']['id'];
        $articleId = $decoded['atomic:results'][2]['data']['id'];

        // Verify in database
        $this->em->clear();
        $article = $this->em->find(Article::class, $articleId);
        self::assertNotNull($article);
        self::assertCount(2, $article->getTags());

        $tagIds = array_map(fn($tag) => $tag->getId(), $article->getTags()->toArray());
        self::assertContains($tag1Id, $tagIds);
        self::assertContains($tag2Id, $tagIds);
    }

    /**
     * Test D4: Use lid in relationship operation.
     */
    public function testLidInRelationshipOperation(): void
    {
        $operations = [
            [
                'op' => 'add',
                'ref' => ['type' => 'authors'],
                'data' => [
                    'type' => 'authors',
                    'lid' => 'temp-author',
                    'attributes' => [
                        'name' => 'Test Author',
                        'email' => 'test@example.com',
                    ],
                ],
            ],
            [
                'op' => 'add',
                'ref' => ['type' => 'articles'],
                'data' => [
                    'type' => 'articles',
                    'lid' => 'temp-article',
                    'attributes' => [
                        'title' => 'Test Article',
                        'content' => 'Content',
                    ],
                ],
            ],
            [
                'op' => 'update',
                'ref' => ['type' => 'articles', 'lid' => 'temp-article', 'relationship' => 'author'],
                'data' => ['type' => 'authors', 'lid' => 'temp-author'],
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
     * Test D5: Complex scenario with multiple lids.
     */
    public function testComplexLidScenario(): void
    {
        $operations = [
            // Create author with lid
            [
                'op' => 'add',
                'ref' => ['type' => 'authors'],
                'data' => [
                    'type' => 'authors',
                    'lid' => 'author-1',
                    'attributes' => [
                        'name' => 'John Doe',
                        'email' => 'john@example.com',
                    ],
                ],
            ],
            // Create tags with lids
            [
                'op' => 'add',
                'ref' => ['type' => 'tags'],
                'data' => [
                    'type' => 'tags',
                    'lid' => 'tag-php',
                    'attributes' => ['name' => 'PHP'],
                ],
            ],
            [
                'op' => 'add',
                'ref' => ['type' => 'tags'],
                'data' => [
                    'type' => 'tags',
                    'lid' => 'tag-symfony',
                    'attributes' => ['name' => 'Symfony'],
                ],
            ],
            // Create article with relationships using lids
            [
                'op' => 'add',
                'ref' => ['type' => 'articles'],
                'data' => [
                    'type' => 'articles',
                    'lid' => 'article-1',
                    'attributes' => [
                        'title' => 'Getting Started with Symfony',
                        'content' => 'This is a comprehensive guide...',
                    ],
                    'relationships' => [
                        'author' => [
                            'data' => ['type' => 'authors', 'lid' => 'author-1'],
                        ],
                        'tags' => [
                            'data' => [
                                ['type' => 'tags', 'lid' => 'tag-php'],
                                ['type' => 'tags', 'lid' => 'tag-symfony'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->executeAtomicRequest($operations);

        self::assertSame(200, $response->getStatusCode());

        $decoded = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $results = $decoded['atomic:results'];
        self::assertCount(4, $results);

        $authorId = $results[0]['data']['id'];
        $tag1Id = $results[1]['data']['id'];
        $tag2Id = $results[2]['data']['id'];
        $articleId = $results[3]['data']['id'];

        // Verify in database
        $this->em->clear();
        $article = $this->em->find(Article::class, $articleId);
        self::assertNotNull($article);

        // Verify author relationship
        self::assertNotNull($article->getAuthor());
        self::assertSame($authorId, $article->getAuthor()->getId());

        // Verify tags relationship
        self::assertCount(2, $article->getTags());
        $tagIds = array_map(fn($tag) => $tag->getId(), $article->getTags()->toArray());
        self::assertContains($tag1Id, $tagIds);
        self::assertContains($tag2Id, $tagIds);
    }

    /**
     * Test D6: Duplicate lid should fail with proper rollback.
     */
    public function testDuplicateLidThrowsErrorAndRollsBack(): void
    {
        $initialCount = $this->getDatabaseResourceCount('authors');

        $operations = [
            [
                'op' => 'add',
                'ref' => ['type' => 'authors'],
                'data' => [
                    'type' => 'authors',
                    'lid' => 'duplicate-lid',
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
                    'lid' => 'duplicate-lid', // Same lid!
                    'attributes' => [
                        'name' => 'Author 2',
                        'email' => 'author2@example.com',
                    ],
                ],
            ],
        ];

        // Expect BadRequestException for duplicate lid
        $this->expectException(\AlexFigures\Symfony\Http\Exception\BadRequestException::class);
        $this->expectExceptionMessage('Duplicate local identifier');

        try {
            $this->executeAtomicRequest($operations);
        } finally {
            // Verify rollback happened
            $this->em->clear();
            $finalCount = $this->getDatabaseResourceCount('authors');
            self::assertSame($initialCount, $finalCount, 'All operations should be rolled back');
        }
    }
}

