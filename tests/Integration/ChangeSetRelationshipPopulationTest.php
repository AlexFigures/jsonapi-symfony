<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Integration;

use JsonApi\Symfony\Http\Write\ChangeSetFactory;
use JsonApi\Symfony\Tests\Integration\Fixtures\Entity\Article;
use JsonApi\Symfony\Tests\Integration\Fixtures\Entity\Author;
use JsonApi\Symfony\Tests\Integration\Fixtures\Entity\Tag;

/**
 * Tests that verify ChangeSet is properly populated with relationships
 * and that the unified data flow works correctly.
 *
 * @group integration
 */
final class ChangeSetRelationshipPopulationTest extends DoctrineIntegrationTestCase
{
    private ChangeSetFactory $changeSetFactory;

    protected function getDatabaseUrl(): string
    {
        return $_ENV['DATABASE_URL']
            ?? 'sqlite:///:memory:';
    }

    protected function getPlatform(): string
    {
        return 'sqlite';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->changeSetFactory = new ChangeSetFactory($this->registry);
    }

    /**
     * Test that creating a resource with a to-one relationship populates
     * the ChangeSet correctly and applies the relationship.
     */
    public function testCreateWithToOneRelationshipPopulatesChangeSet(): void
    {
        // Setup: Create an author
        $author = new Author();
        $author->setId('author-1');
        $author->setName('John Doe');
        $author->setEmail('john@example.com');
        $this->em->persist($author);
        $this->em->flush();
        $this->em->clear();

        // Create article with author relationship
        $changes = $this->changeSetFactory->fromInput(
            'articles',
            ['title' => 'Test Article', 'content' => 'Article content'],
            ['author' => ['data' => ['type' => 'authors', 'id' => 'author-1']]]
        );

        // Verify ChangeSet is populated correctly
        $this->assertEquals(['title' => 'Test Article', 'content' => 'Article content'], $changes->attributes);
        $this->assertEquals(
            ['author' => ['data' => ['type' => 'authors', 'id' => 'author-1']]],
            $changes->relationships
        );

        // Create the article
        $article = $this->transactionManager->transactional(function () use ($changes) {
            return $this->validatingPersister->create('articles', $changes, 'article-1');
        });

        // Verify the relationship was applied
        $this->assertInstanceOf(Article::class, $article);
        $this->assertEquals('Test Article', $article->getTitle());
        $this->assertNotNull($article->getAuthor());
        $this->assertEquals('author-1', $article->getAuthor()->getId());
    }

    /**
     * Test that creating a resource with to-many relationships populates
     * the ChangeSet correctly and applies the relationships.
     */
    public function testCreateWithToManyRelationshipsPopulatesChangeSet(): void
    {
        // Setup: Create tags
        $tag1 = new Tag();
        $tag1->setId('tag-1');
        $tag1->setName('Tag 1');
        $tag2 = new Tag();
        $tag2->setId('tag-2');
        $tag2->setName('Tag 2');
        $tag3 = new Tag();
        $tag3->setId('tag-3');
        $tag3->setName('Tag 3');
        $this->em->persist($tag1);
        $this->em->persist($tag2);
        $this->em->persist($tag3);
        $this->em->flush();
        $this->em->clear();

        // Create article with tags relationship
        $changes = $this->changeSetFactory->fromInput(
            'articles',
            ['title' => 'Test Article', 'content' => 'Article content'],
            [
                'tags' => [
                    'data' => [
                        ['type' => 'tags', 'id' => 'tag-1'],
                        ['type' => 'tags', 'id' => 'tag-2'],
                        ['type' => 'tags', 'id' => 'tag-3'],
                    ],
                ],
            ]
        );

        // Verify ChangeSet is populated correctly
        $this->assertEquals(['title' => 'Test Article', 'content' => 'Article content'], $changes->attributes);
        $this->assertArrayHasKey('tags', $changes->relationships);
        $this->assertCount(3, $changes->relationships['tags']['data']);

        // Create the article
        $article = $this->transactionManager->transactional(function () use ($changes) {
            return $this->validatingPersister->create('articles', $changes, 'article-2');
        });

        // Verify the relationships were applied
        $this->assertInstanceOf(Article::class, $article);
        $this->assertEquals('Test Article', $article->getTitle());
        $this->assertCount(3, $article->getTags());
    }

    /**
     * Test that updating a resource with relationships populates
     * the ChangeSet correctly and applies the relationships.
     */
    public function testUpdateWithRelationshipsPopulatesChangeSet(): void
    {
        // Setup: Create article with author
        $author1 = new Author();
        $author1->setId('author-1');
        $author1->setName('John Doe');
        $author1->setEmail('john@example.com');
        $author2 = new Author();
        $author2->setId('author-2');
        $author2->setName('Jane Smith');
        $author2->setEmail('jane@example.com');
        $article = new Article();
        $article->setId('article-1');
        $article->setTitle('Original Title');
        $article->setContent('Original content');
        $article->setAuthor($author1);
        $this->em->persist($author1);
        $this->em->persist($author2);
        $this->em->persist($article);
        $this->em->flush();
        $this->em->clear();

        // Update article with new author
        $changes = $this->changeSetFactory->fromInput(
            'articles',
            ['title' => 'Updated Title'],
            ['author' => ['data' => ['type' => 'authors', 'id' => 'author-2']]]
        );

        // Verify ChangeSet is populated correctly
        $this->assertEquals(['title' => 'Updated Title'], $changes->attributes);
        $this->assertEquals(
            ['author' => ['data' => ['type' => 'authors', 'id' => 'author-2']]],
            $changes->relationships
        );

        // Update the article
        $article = $this->transactionManager->transactional(function () use ($changes) {
            return $this->validatingPersister->update('articles', 'article-1', $changes);
        });

        // Verify the relationship was updated
        $this->assertInstanceOf(Article::class, $article);
        $this->assertEquals('Updated Title', $article->getTitle());
        $this->assertNotNull($article->getAuthor());
        $this->assertEquals('author-2', $article->getAuthor()->getId());
    }

    /**
     * Test that creating a resource with both attributes and relationships
     * works correctly in a single unified flow.
     */
    public function testCreateWithBothAttributesAndRelationshipsUnifiedFlow(): void
    {
        // Setup: Create author and tags
        $author = new Author();
        $author->setId('author-1');
        $author->setName('John Doe');
        $author->setEmail('john@example.com');
        $tag1 = new Tag();
        $tag1->setId('tag-1');
        $tag1->setName('Tag 1');
        $tag2 = new Tag();
        $tag2->setId('tag-2');
        $tag2->setName('Tag 2');
        $this->em->persist($author);
        $this->em->persist($tag1);
        $this->em->persist($tag2);
        $this->em->flush();
        $this->em->clear();

        // Create article with both attributes and relationships
        $changes = $this->changeSetFactory->fromInput(
            'articles',
            [
                'title' => 'Complete Article',
                'content' => 'Full article content',
            ],
            [
                'author' => ['data' => ['type' => 'authors', 'id' => 'author-1']],
                'tags' => [
                    'data' => [
                        ['type' => 'tags', 'id' => 'tag-1'],
                        ['type' => 'tags', 'id' => 'tag-2'],
                    ],
                ],
            ]
        );

        // Create the article in a single transaction
        $article = $this->transactionManager->transactional(function () use ($changes) {
            return $this->validatingPersister->create('articles', $changes, 'article-3');
        });

        // Verify everything was applied correctly
        $this->assertInstanceOf(Article::class, $article);
        $this->assertEquals('Complete Article', $article->getTitle());
        $this->assertEquals('Full article content', $article->getContent());
        $this->assertNotNull($article->getAuthor());
        $this->assertEquals('author-1', $article->getAuthor()->getId());
        $this->assertCount(2, $article->getTags());
    }

    /**
     * Test backward compatibility: fromAttributes() still works but triggers deprecation.
     */
    public function testBackwardCompatibilityFromAttributesStillWorks(): void
    {
        // Suppress deprecation warning for this test
        $errorReporting = error_reporting();
        error_reporting($errorReporting & ~E_USER_DEPRECATED);

        $changes = $this->changeSetFactory->fromAttributes(
            'articles',
            ['title' => 'Test Article', 'content' => 'Article content']
        );

        error_reporting($errorReporting);

        // Verify ChangeSet has attributes but no relationships
        $this->assertEquals(['title' => 'Test Article', 'content' => 'Article content'], $changes->attributes);
        $this->assertEquals([], $changes->relationships);

        // Verify it still works for creating resources
        $article = $this->transactionManager->transactional(function () use ($changes) {
            return $this->validatingPersister->create('articles', $changes, 'article-4');
        });

        $this->assertInstanceOf(Article::class, $article);
        $this->assertEquals('Test Article', $article->getTitle());
    }
}

