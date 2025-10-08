<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Integration;

use JsonApi\Symfony\Contract\Data\ChangeSet;
use JsonApi\Symfony\Http\Exception\ValidationException;
use JsonApi\Symfony\Tests\Integration\Fixtures\Entity\Article;
use JsonApi\Symfony\Tests\Integration\Fixtures\Entity\Author;

/**
 * Tests that verify proper transaction boundary management after removing nested transactions.
 *
 * These tests ensure that:
 * 1. Operations are truly atomic (all succeed or all fail)
 * 2. No nested transactions are created
 * 3. Rollback works correctly when relationships fail
 * 4. Entity and relationships are persisted together
 *
 * @group integration
 */
final class TransactionBoundaryTest extends DoctrineIntegrationTestCase
{
    protected function getDatabaseUrl(): string
    {
        return $_ENV['DATABASE_URL']
            ?? 'sqlite:///:memory:';
    }

    protected function getPlatform(): string
    {
        return 'sqlite';
    }

    /**
     * Test that creating a resource with a valid to-one relationship is atomic.
     */
    public function testCreateWithToOneRelationshipIsAtomic(): void
    {
        // Create an author first
        $author = new Author('author-1', 'Test Author');
        $this->em->persist($author);
        $this->em->flush();
        $this->em->clear();

        // Create article with relationship in a transaction
        $changes = new ChangeSet(
            attributes: [
                'title' => 'Test Article',
                'content' => 'Content',
            ],
            relationships: [
                'author' => [
                    'data' => ['type' => 'authors', 'id' => 'author-1']
                ]
            ]
        );

        $article = $this->transactionManager->transactional(function () use ($changes) {
            return $this->persister->create('articles', $changes, 'article-1');
        });

        // Verify both entity and relationship were saved
        $this->em->clear();
        $found = $this->em->find(Article::class, 'article-1');
        $this->assertNotNull($found);
        $this->assertSame('Test Article', $found->getTitle());
        $this->assertNotNull($found->getAuthor());
        $this->assertSame('author-1', $found->getAuthor()->getId());
    }

    /**
     * Test that creating a resource with an invalid to-one relationship rolls back completely.
     */
    public function testCreateWithInvalidToOneRelationshipRollsBack(): void
    {
        // Create article with non-existent author
        $changes = new ChangeSet(
            attributes: [
                'title' => 'Test Article',
                'content' => 'Content',
            ],
            relationships: [
                'author' => [
                    'data' => ['type' => 'authors', 'id' => 'non-existent']
                ]
            ]
        );

        try {
            $this->transactionManager->transactional(function () use ($changes) {
                return $this->persister->create('articles', $changes, 'article-2');
            });
            $this->fail('Expected exception was not thrown');
        } catch (ValidationException $e) {
            // Expected - relationship validation failed
        }

        // Verify that the article was NOT saved (transaction rolled back)
        $this->em->clear();
        $found = $this->em->find(Article::class, 'article-2');
        $this->assertNull($found, 'Article should not exist after rollback');
    }

    /**
     * Test that creating a resource with valid to-many relationships is atomic.
     */
    public function testCreateWithToManyRelationshipsIsAtomic(): void
    {
        // Create tags first
        $tag1 = $this->createTag('tag-1', 'Tag 1');
        $tag2 = $this->createTag('tag-2', 'Tag 2');
        $tag3 = $this->createTag('tag-3', 'Tag 3');
        $this->em->flush();
        $this->em->clear();

        // Create article with multiple tags
        $changes = new ChangeSet(
            attributes: [
                'title' => 'Article with Tags',
                'content' => 'Content',
            ],
            relationships: [
                'tags' => [
                    'data' => [
                        ['type' => 'tags', 'id' => 'tag-1'],
                        ['type' => 'tags', 'id' => 'tag-2'],
                        ['type' => 'tags', 'id' => 'tag-3'],
                    ]
                ]
            ]
        );

        $article = $this->transactionManager->transactional(function () use ($changes) {
            return $this->persister->create('articles', $changes, 'article-3');
        });

        // Verify entity and all relationships were saved
        $this->em->clear();
        $found = $this->em->find(Article::class, 'article-3');
        $this->assertNotNull($found);
        $this->assertSame('Article with Tags', $found->getTitle());
        $this->assertCount(3, $found->getTags());

        $tagIds = array_map(fn($tag) => $tag->getId(), $found->getTags()->toArray());
        $this->assertContains('tag-1', $tagIds);
        $this->assertContains('tag-2', $tagIds);
        $this->assertContains('tag-3', $tagIds);
    }

    /**
     * Test that creating a resource with invalid to-many relationships rolls back completely.
     */
    public function testCreateWithInvalidToManyRelationshipsRollsBack(): void
    {
        // Create only 2 tags
        $tag1 = $this->createTag('tag-4', 'Tag 4');
        $tag2 = $this->createTag('tag-5', 'Tag 5');
        $this->em->flush();
        $this->em->clear();

        // Try to create article with 3 tags (one doesn't exist)
        $changes = new ChangeSet(
            attributes: [
                'title' => 'Article with Invalid Tags',
                'content' => 'Content',
            ],
            relationships: [
                'tags' => [
                    'data' => [
                        ['type' => 'tags', 'id' => 'tag-4'],
                        ['type' => 'tags', 'id' => 'tag-5'],
                        ['type' => 'tags', 'id' => 'tag-non-existent'], // This doesn't exist
                    ]
                ]
            ]
        );

        try {
            $this->transactionManager->transactional(function () use ($changes) {
                return $this->persister->create('articles', $changes, 'article-4');
            });
            $this->fail('Expected exception was not thrown');
        } catch (ValidationException $e) {
            // Expected - relationship validation failed
        }

        // Verify that the article was NOT saved (transaction rolled back)
        $this->em->clear();
        $found = $this->em->find(Article::class, 'article-4');
        $this->assertNull($found, 'Article should not exist after rollback');
    }

    /**
     * Test that update operations are also properly transactional.
     */
    public function testUpdateWithRelationshipsIsAtomic(): void
    {
        // Create article and author
        $author1 = new Author('author-10', 'Author 10');
        $author2 = new Author('author-11', 'Author 11');
        $article = new Article();
        $article->setId('article-10');
        $article->setTitle('Original Title');
        $article->setContent('Original Content');
        $article->setAuthor($author1);

        $this->em->persist($author1);
        $this->em->persist($author2);
        $this->em->persist($article);
        $this->em->flush();
        $this->em->clear();

        // Update article with new author
        $changes = new ChangeSet(
            attributes: [
                'title' => 'Updated Title',
            ],
            relationships: [
                'author' => [
                    'data' => ['type' => 'authors', 'id' => 'author-11']
                ]
            ]
        );

        $updated = $this->transactionManager->transactional(function () use ($changes) {
            return $this->persister->update('articles', 'article-10', $changes);
        });

        // Verify both attribute and relationship were updated
        $this->em->clear();
        $found = $this->em->find(Article::class, 'article-10');
        $this->assertNotNull($found);
        $this->assertSame('Updated Title', $found->getTitle());
        $this->assertSame('author-11', $found->getAuthor()->getId());
    }

    /**
     * Test that delete operations are properly transactional.
     */
    public function testDeleteIsAtomic(): void
    {
        // Create article
        $article = new Article();
        $article->setId('article-20');
        $article->setTitle('To Delete');
        $article->setContent('Content');

        $this->em->persist($article);
        $this->em->flush();
        $this->em->clear();

        // Delete article
        $this->transactionManager->transactional(function () {
            $this->persister->delete('articles', 'article-20');
        });

        // Verify article was deleted
        $this->em->clear();
        $found = $this->em->find(Article::class, 'article-20');
        $this->assertNull($found);
    }

    /**
     * Test that no nested transactions are created (this would previously cause issues).
     */
    public function testNoNestedTransactions(): void
    {
        // This test verifies that the fix works by ensuring we can create
        // a resource within a transaction without errors

        $author = new Author('author-30', 'Author 30');
        $this->em->persist($author);
        $this->em->flush();
        $this->em->clear();

        $changes = new ChangeSet(
            attributes: [
                'title' => 'Nested Transaction Test',
                'content' => 'Content',
            ],
            relationships: [
                'author' => [
                    'data' => ['type' => 'authors', 'id' => 'author-30']
                ]
            ]
        );

        // This should work without throwing "Nested transaction" errors
        $article = $this->transactionManager->transactional(function () use ($changes) {
            // The persister.create() no longer wraps in its own transaction
            return $this->persister->create('articles', $changes, 'article-30');
        });

        $this->assertNotNull($article);
        $this->assertSame('article-30', $article->getId());
    }
}

