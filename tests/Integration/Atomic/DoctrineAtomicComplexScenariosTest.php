<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Atomic;

use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Article;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Author;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Tag;

/**
 * Test E: Complex scenarios for atomic operations.
 *
 * Covers:
 * - Cascading operations (create parent + children in one request)
 * - Bidirectional relationship synchronization
 * - Multiple LIDs with complex relationship graphs
 * - Deep relationship chains
 */
final class DoctrineAtomicComplexScenariosTest extends DoctrineAtomicTestCase
{
    /**
     * Test E2.1: Cascading create operations.
     *
     * Create author + multiple articles + tags in a single atomic request.
     */
    public function testCascadingCreateOperations(): void
    {
        $operations = [
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
            [
                'op' => 'add',
                'ref' => ['type' => 'articles'],
                'data' => [
                    'type' => 'articles',
                    'lid' => 'article-1',
                    'attributes' => [
                        'title' => 'PHP Best Practices',
                        'content' => 'Content about PHP',
                    ],
                    'relationships' => [
                        'author' => [
                            'data' => ['type' => 'authors', 'lid' => 'author-1'],
                        ],
                        'tags' => [
                            'data' => [
                                ['type' => 'tags', 'lid' => 'tag-php'],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'op' => 'add',
                'ref' => ['type' => 'articles'],
                'data' => [
                    'type' => 'articles',
                    'attributes' => [
                        'title' => 'Symfony Guide',
                        'content' => 'Content about Symfony',
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
        $authorId = $decoded['atomic:results'][0]['data']['id'];
        $tagPhpId = $decoded['atomic:results'][1]['data']['id'];
        $tagSymfonyId = $decoded['atomic:results'][2]['data']['id'];
        $article1Id = $decoded['atomic:results'][3]['data']['id'];
        $article2Id = $decoded['atomic:results'][4]['data']['id'];

        // Verify in database
        $this->em->clear();

        $author = $this->em->find(Author::class, $authorId);
        self::assertNotNull($author);
        self::assertCount(2, $author->getArticles(), 'Author should have 2 articles');

        $article1 = $this->em->find(Article::class, $article1Id);
        self::assertNotNull($article1);
        self::assertSame($authorId, $article1->getAuthor()->getId());
        self::assertCount(1, $article1->getTags());
        self::assertSame($tagPhpId, $article1->getTags()->first()->getId());

        $article2 = $this->em->find(Article::class, $article2Id);
        self::assertNotNull($article2);
        self::assertSame($authorId, $article2->getAuthor()->getId());
        self::assertCount(2, $article2->getTags());

        $tagIds = array_map(fn($tag) => $tag->getId(), $article2->getTags()->toArray());
        self::assertContains($tagPhpId, $tagIds);
        self::assertContains($tagSymfonyId, $tagIds);
    }

    /**
     * Test E2.2: Bidirectional relationship synchronization.
     *
     * Verify that bidirectional relationships (Author <-> Article) are
     * properly synchronized on both sides.
     */
    public function testBidirectionalRelationshipSync(): void
    {
        $operations = [
            [
                'op' => 'add',
                'ref' => ['type' => 'authors'],
                'data' => [
                    'type' => 'authors',
                    'lid' => 'author-1',
                    'attributes' => [
                        'name' => 'Jane Smith',
                        'email' => 'jane@example.com',
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
                        'content' => 'Content 1',
                    ],
                    'relationships' => [
                        'author' => [
                            'data' => ['type' => 'authors', 'lid' => 'author-1'],
                        ],
                    ],
                ],
            ],
            [
                'op' => 'add',
                'ref' => ['type' => 'articles'],
                'data' => [
                    'type' => 'articles',
                    'attributes' => [
                        'title' => 'Article 2',
                        'content' => 'Content 2',
                    ],
                    'relationships' => [
                        'author' => [
                            'data' => ['type' => 'authors', 'lid' => 'author-1'],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->executeAtomicRequest($operations);

        self::assertSame(200, $response->getStatusCode());

        $decoded = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $authorId = $decoded['atomic:results'][0]['data']['id'];
        $article1Id = $decoded['atomic:results'][1]['data']['id'];
        $article2Id = $decoded['atomic:results'][2]['data']['id'];

        // Verify bidirectional sync
        $this->em->clear();

        $author = $this->em->find(Author::class, $authorId);
        self::assertNotNull($author);
        self::assertCount(2, $author->getArticles(), 'Author should have 2 articles (inverse side)');

        $articleIds = array_map(fn($article) => $article->getId(), $author->getArticles()->toArray());
        self::assertContains($article1Id, $articleIds);
        self::assertContains($article2Id, $articleIds);

        $article1 = $this->em->find(Article::class, $article1Id);
        self::assertNotNull($article1);
        self::assertSame($authorId, $article1->getAuthor()->getId(), 'Article should reference author (owning side)');

        $article2 = $this->em->find(Article::class, $article2Id);
        self::assertNotNull($article2);
        self::assertSame($authorId, $article2->getAuthor()->getId(), 'Article should reference author (owning side)');
    }

    /**
     * Test E2.3: Update bidirectional relationship.
     *
     * Change article's author and verify both sides are updated.
     */
    public function testUpdateBidirectionalRelationship(): void
    {
        // Create initial data
        $author1 = new Author();
        $author1->setName('Author 1');
        $author1->setEmail('author1@example.com');
        $this->em->persist($author1);

        $author2 = new Author();
        $author2->setName('Author 2');
        $author2->setEmail('author2@example.com');
        $this->em->persist($author2);

        $article = new Article();
        $article->setTitle('Article');
        $article->setContent('Content');
        $article->setAuthor($author1);
        $this->em->persist($article);

        $this->em->flush();
        $author1Id = $author1->getId();
        $author2Id = $author2->getId();
        $articleId = $article->getId();
        $this->em->clear();

        // Update article's author
        $operations = [
            [
                'op' => 'update',
                'ref' => ['type' => 'articles', 'id' => $articleId],
                'data' => [
                    'type' => 'articles',
                    'id' => $articleId,
                    'relationships' => [
                        'author' => [
                            'data' => ['type' => 'authors', 'id' => $author2Id],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->executeAtomicRequest($operations);

        self::assertSame(200, $response->getStatusCode());

        // Verify bidirectional sync
        $this->em->clear();

        $updatedArticle = $this->em->find(Article::class, $articleId);
        self::assertNotNull($updatedArticle);
        self::assertSame($author2Id, $updatedArticle->getAuthor()->getId());

        $updatedAuthor1 = $this->em->find(Author::class, $author1Id);
        self::assertNotNull($updatedAuthor1);
        self::assertCount(0, $updatedAuthor1->getArticles(), 'Author 1 should have no articles');

        $updatedAuthor2 = $this->em->find(Author::class, $author2Id);
        self::assertNotNull($updatedAuthor2);
        self::assertCount(1, $updatedAuthor2->getArticles(), 'Author 2 should have 1 article');
        self::assertSame($articleId, $updatedAuthor2->getArticles()->first()->getId());
    }

    /**
     * Test E2.4: Multiple LIDs with complex relationship graph.
     *
     * Create a complex graph: 2 authors, 3 articles, 4 tags with various relationships.
     */
    public function testComplexRelationshipGraph(): void
    {
        $operations = [
            // Create 2 authors
            [
                'op' => 'add',
                'ref' => ['type' => 'authors'],
                'data' => [
                    'type' => 'authors',
                    'lid' => 'author-alice',
                    'attributes' => [
                        'name' => 'Alice',
                        'email' => 'alice@example.com',
                    ],
                ],
            ],
            [
                'op' => 'add',
                'ref' => ['type' => 'authors'],
                'data' => [
                    'type' => 'authors',
                    'lid' => 'author-bob',
                    'attributes' => [
                        'name' => 'Bob',
                        'email' => 'bob@example.com',
                    ],
                ],
            ],
            // Create 4 tags
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
                    'lid' => 'tag-js',
                    'attributes' => ['name' => 'JavaScript'],
                ],
            ],
            [
                'op' => 'add',
                'ref' => ['type' => 'tags'],
                'data' => [
                    'type' => 'tags',
                    'lid' => 'tag-web',
                    'attributes' => ['name' => 'Web'],
                ],
            ],
            [
                'op' => 'add',
                'ref' => ['type' => 'tags'],
                'data' => [
                    'type' => 'tags',
                    'lid' => 'tag-api',
                    'attributes' => ['name' => 'API'],
                ],
            ],
            // Create 3 articles with different relationship combinations
            [
                'op' => 'add',
                'ref' => ['type' => 'articles'],
                'data' => [
                    'type' => 'articles',
                    'attributes' => [
                        'title' => 'PHP Web APIs',
                        'content' => 'Content',
                    ],
                    'relationships' => [
                        'author' => [
                            'data' => ['type' => 'authors', 'lid' => 'author-alice'],
                        ],
                        'tags' => [
                            'data' => [
                                ['type' => 'tags', 'lid' => 'tag-php'],
                                ['type' => 'tags', 'lid' => 'tag-web'],
                                ['type' => 'tags', 'lid' => 'tag-api'],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'op' => 'add',
                'ref' => ['type' => 'articles'],
                'data' => [
                    'type' => 'articles',
                    'attributes' => [
                        'title' => 'JavaScript Web Development',
                        'content' => 'Content',
                    ],
                    'relationships' => [
                        'author' => [
                            'data' => ['type' => 'authors', 'lid' => 'author-bob'],
                        ],
                        'tags' => [
                            'data' => [
                                ['type' => 'tags', 'lid' => 'tag-js'],
                                ['type' => 'tags', 'lid' => 'tag-web'],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'op' => 'add',
                'ref' => ['type' => 'articles'],
                'data' => [
                    'type' => 'articles',
                    'attributes' => [
                        'title' => 'Building APIs',
                        'content' => 'Content',
                    ],
                    'relationships' => [
                        'author' => [
                            'data' => ['type' => 'authors', 'lid' => 'author-alice'],
                        ],
                        'tags' => [
                            'data' => [
                                ['type' => 'tags', 'lid' => 'tag-api'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->executeAtomicRequest($operations);

        self::assertSame(200, $response->getStatusCode());

        $decoded = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        // Verify counts
        $this->em->clear();
        self::assertSame(2, $this->getDatabaseResourceCount('authors'));
        self::assertSame(4, $this->getDatabaseResourceCount('tags'));
        self::assertSame(3, $this->getDatabaseResourceCount('articles'));

        // Verify Alice has 2 articles
        $aliceId = $decoded['atomic:results'][0]['data']['id'];
        $alice = $this->em->find(Author::class, $aliceId);
        self::assertNotNull($alice);
        self::assertCount(2, $alice->getArticles());

        // Verify Bob has 1 article
        $bobId = $decoded['atomic:results'][1]['data']['id'];
        $bob = $this->em->find(Author::class, $bobId);
        self::assertNotNull($bob);
        self::assertCount(1, $bob->getArticles());

        // Verify first article has 3 tags
        $article1Id = $decoded['atomic:results'][6]['data']['id'];
        $article1 = $this->em->find(Article::class, $article1Id);
        self::assertNotNull($article1);
        self::assertCount(3, $article1->getTags());

        // Verify second article has 2 tags
        $article2Id = $decoded['atomic:results'][7]['data']['id'];
        $article2 = $this->em->find(Article::class, $article2Id);
        self::assertNotNull($article2);
        self::assertCount(2, $article2->getTags());

        // Verify third article has 1 tag
        $article3Id = $decoded['atomic:results'][8]['data']['id'];
        $article3 = $this->em->find(Article::class, $article3Id);
        self::assertNotNull($article3);
        self::assertCount(1, $article3->getTags());
    }

    /**
     * Test E2.5: Remove with bidirectional relationship cleanup.
     *
     * Verify that removing an article properly cleans up bidirectional relationships.
     */
    public function testRemoveWithBidirectionalCleanup(): void
    {
        // Create initial data
        $author = new Author();
        $author->setName('Author');
        $author->setEmail('author@example.com');
        $this->em->persist($author);

        $article1 = new Article();
        $article1->setTitle('Article 1');
        $article1->setContent('Content 1');
        $article1->setAuthor($author);
        $this->em->persist($article1);

        $article2 = new Article();
        $article2->setTitle('Article 2');
        $article2->setContent('Content 2');
        $article2->setAuthor($author);
        $this->em->persist($article2);

        $this->em->flush();
        $authorId = $author->getId();
        $article1Id = $article1->getId();
        $article2Id = $article2->getId();
        $this->em->clear();

        // Remove article1
        $operations = [
            [
                'op' => 'remove',
                'ref' => ['type' => 'articles', 'id' => $article1Id],
            ],
        ];

        $response = $this->executeAtomicRequest($operations);

        self::assertSame(204, $response->getStatusCode());

        // Verify article1 is removed
        $this->em->clear();
        $removedArticle = $this->em->find(Article::class, $article1Id);
        self::assertNull($removedArticle);

        // Verify author still has article2
        $updatedAuthor = $this->em->find(Author::class, $authorId);
        self::assertNotNull($updatedAuthor);
        self::assertCount(1, $updatedAuthor->getArticles());
        self::assertSame($article2Id, $updatedAuthor->getArticles()->first()->getId());
    }
}

