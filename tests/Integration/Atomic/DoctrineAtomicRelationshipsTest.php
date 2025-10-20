<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Atomic;

use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Article;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Author;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Tag;

/**
 * Integration tests for Atomic Operations with Doctrine - Relationships (Phase 2).
 *
 * Tests relationship operations with real PostgreSQL database:
 * - To-one relationships (ManyToOne)
 * - To-many relationships (ManyToMany)
 * - Relationship updates within atomic operations
 * - Bidirectional relationship synchronization
 */
final class DoctrineAtomicRelationshipsTest extends DoctrineAtomicTestCase
{
    /**
     * Test C1: Create resource with to-one relationship.
     */
    public function testAddResourceWithToOneRelationship(): void
    {
        // Create author first
        $author = new Author();
        $author->setName('John Doe');
        $author->setEmail('john@example.com');
        $this->em->persist($author);
        $this->em->flush();
        $this->em->clear();

        $authorId = $author->getId();

        // Create article with author relationship
        $operations = [
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
                            'data' => ['type' => 'authors', 'id' => $authorId],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->executeAtomicRequest($operations);

        self::assertSame(200, $response->getStatusCode());

        $decoded = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertArrayHasKey('atomic:results', $decoded);

        $articleId = $decoded['atomic:results'][0]['data']['id'];

        // Verify in database
        $this->em->clear();
        $article = $this->em->find(Article::class, $articleId);
        self::assertNotNull($article);
        self::assertNotNull($article->getAuthor());
        self::assertSame($authorId, $article->getAuthor()->getId());
    }

    /**
     * Test C2: Create resource with to-many relationship.
     */
    public function testAddResourceWithToManyRelationship(): void
    {
        // Create tags first
        $tag1 = new Tag();
        $tag1->setName('PHP');
        $tag2 = new Tag();
        $tag2->setName('Symfony');

        $this->em->persist($tag1);
        $this->em->persist($tag2);
        $this->em->flush();
        $this->em->clear();

        $tag1Id = $tag1->getId();
        $tag2Id = $tag2->getId();

        // Create article with tags relationship
        $operations = [
            [
                'op' => 'add',
                'ref' => ['type' => 'articles'],
                'data' => [
                    'type' => 'articles',
                    'attributes' => [
                        'title' => 'PHP Article',
                        'content' => 'Content about PHP',
                    ],
                    'relationships' => [
                        'tags' => [
                            'data' => [
                                ['type' => 'tags', 'id' => $tag1Id],
                                ['type' => 'tags', 'id' => $tag2Id],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->executeAtomicRequest($operations);

        self::assertSame(200, $response->getStatusCode());

        $decoded = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $articleId = $decoded['atomic:results'][0]['data']['id'];

        // Verify in database
        $this->em->clear();
        $article = $this->em->find(Article::class, $articleId);
        self::assertNotNull($article);
        self::assertCount(2, $article->getTags());

        $tagIds = array_map(fn ($tag) => $tag->getId(), $article->getTags()->toArray());
        self::assertContains($tag1Id, $tagIds);
        self::assertContains($tag2Id, $tagIds);
    }

    /**
     * Test C3: Update to-one relationship using relationship operation.
     */
    public function testUpdateToOneRelationship(): void
    {
        // Create author and article
        $author1 = new Author();
        $author1->setName('Author 1');
        $author1->setEmail('author1@example.com');

        $author2 = new Author();
        $author2->setName('Author 2');
        $author2->setEmail('author2@example.com');

        $article = new Article();
        $article->setTitle('Test Article');
        $article->setContent('Content');
        $article->setAuthor($author1);

        $this->em->persist($author1);
        $this->em->persist($author2);
        $this->em->persist($article);
        $this->em->flush();
        $this->em->clear();

        $articleId = $article->getId();
        $author2Id = $author2->getId();

        // Update article's author
        $operations = [
            [
                'op' => 'update',
                'ref' => ['type' => 'articles', 'id' => $articleId, 'relationship' => 'author'],
                'data' => ['type' => 'authors', 'id' => $author2Id],
            ],
        ];

        $response = $this->executeAtomicRequest($operations);

        // Relationship operations return 204 No Content when all results are empty
        self::assertSame(204, $response->getStatusCode());

        // Verify in database
        $this->em->clear();
        $updatedArticle = $this->em->find(Article::class, $articleId);
        self::assertNotNull($updatedArticle);
        self::assertNotNull($updatedArticle->getAuthor());
        self::assertSame($author2Id, $updatedArticle->getAuthor()->getId());
    }

    /**
     * Test C4: Add to to-many relationship.
     */
    public function testAddToToManyRelationship(): void
    {
        // Create article with one tag
        $tag1 = new Tag();
        $tag1->setName('PHP');
        $tag2 = new Tag();
        $tag2->setName('Symfony');

        $article = new Article();
        $article->setTitle('Test Article');
        $article->setContent('Content');
        $article->addTag($tag1);

        $this->em->persist($tag1);
        $this->em->persist($tag2);
        $this->em->persist($article);
        $this->em->flush();
        $this->em->clear();

        $articleId = $article->getId();
        $tag2Id = $tag2->getId();

        // Add second tag
        $operations = [
            [
                'op' => 'add',
                'ref' => ['type' => 'articles', 'id' => $articleId, 'relationship' => 'tags'],
                'data' => [
                    ['type' => 'tags', 'id' => $tag2Id],
                ],
            ],
        ];

        $response = $this->executeAtomicRequest($operations);

        // Relationship operations return 204 No Content when all results are empty
        self::assertSame(204, $response->getStatusCode());

        // Verify in database
        $this->em->clear();
        $updatedArticle = $this->em->find(Article::class, $articleId);
        self::assertNotNull($updatedArticle);
        self::assertCount(2, $updatedArticle->getTags());
    }

    /**
     * Test C5: Remove from to-many relationship.
     */
    public function testRemoveFromToManyRelationship(): void
    {
        // Create article with two tags
        $tag1 = new Tag();
        $tag1->setName('PHP');
        $tag2 = new Tag();
        $tag2->setName('Symfony');

        $article = new Article();
        $article->setTitle('Test Article');
        $article->setContent('Content');
        $article->addTag($tag1);
        $article->addTag($tag2);

        $this->em->persist($tag1);
        $this->em->persist($tag2);
        $this->em->persist($article);
        $this->em->flush();
        $this->em->clear();

        $articleId = $article->getId();
        $tag1Id = $tag1->getId();

        // Remove first tag
        $operations = [
            [
                'op' => 'remove',
                'ref' => ['type' => 'articles', 'id' => $articleId, 'relationship' => 'tags'],
                'data' => [
                    ['type' => 'tags', 'id' => $tag1Id],
                ],
            ],
        ];

        $response = $this->executeAtomicRequest($operations);

        // Relationship operations return 204 No Content when all results are empty
        self::assertSame(204, $response->getStatusCode());

        // Verify in database
        $this->em->clear();
        $updatedArticle = $this->em->find(Article::class, $articleId);
        self::assertNotNull($updatedArticle);
        self::assertCount(1, $updatedArticle->getTags());

        $remainingTagIds = array_map(fn ($tag) => $tag->getId(), $updatedArticle->getTags()->toArray());
        self::assertNotContains($tag1Id, $remainingTagIds);
    }

    /**
     * Test C6: Replace to-many relationship.
     */
    public function testReplaceToManyRelationship(): void
    {
        // Create article with two tags
        $tag1 = new Tag();
        $tag1->setName('PHP');
        $tag2 = new Tag();
        $tag2->setName('Symfony');
        $tag3 = new Tag();
        $tag3->setName('Doctrine');

        $article = new Article();
        $article->setTitle('Test Article');
        $article->setContent('Content');
        $article->addTag($tag1);
        $article->addTag($tag2);

        $this->em->persist($tag1);
        $this->em->persist($tag2);
        $this->em->persist($tag3);
        $this->em->persist($article);
        $this->em->flush();
        $this->em->clear();

        $articleId = $article->getId();
        $tag3Id = $tag3->getId();

        // Replace all tags with just tag3
        $operations = [
            [
                'op' => 'update',
                'ref' => ['type' => 'articles', 'id' => $articleId, 'relationship' => 'tags'],
                'data' => [
                    ['type' => 'tags', 'id' => $tag3Id],
                ],
            ],
        ];

        $response = $this->executeAtomicRequest($operations);

        // Relationship operations return 204 No Content when all results are empty
        self::assertSame(204, $response->getStatusCode());

        // Verify in database
        $this->em->clear();
        $updatedArticle = $this->em->find(Article::class, $articleId);
        self::assertNotNull($updatedArticle);
        self::assertCount(1, $updatedArticle->getTags());
        self::assertSame($tag3Id, $updatedArticle->getTags()->first()->getId());
    }
}
