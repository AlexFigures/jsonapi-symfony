<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Integration;

use JsonApi\Symfony\Contract\Data\ChangeSet;
use JsonApi\Symfony\Http\Exception\ValidationException;
use JsonApi\Symfony\Tests\Integration\Fixtures\Entity\Article;
use JsonApi\Symfony\Tests\Integration\Fixtures\Entity\Author;
use JsonApi\Symfony\Tests\Integration\Fixtures\Entity\Tag;
use Symfony\Component\Uid\Uuid;

/**
 * Tests complex entities with multiple relationships to ensure stable save/update operations.
 * 
 * Covers scenarios similar to real-world usage:
 * - Creating entities with to-one and to-many relationships
 * - Updating relationships independently
 * - Handling relationship validation errors
 * - Testing relationship denormalization with JSON:API format
 */
final class ComplexRelationshipsTest extends DoctrineIntegrationTestCase
{
    private Author $author1;
    private Author $author2;
    private Tag $tag1;
    private Tag $tag2;
    private Tag $tag3;

    protected function getDatabaseUrl(): string
    {
        return $_ENV['DATABASE_URL_POSTGRES']
            ?? 'postgresql://jsonapi:secret@localhost:5432/jsonapi_test?serverVersion=16&charset=utf8';
    }

    protected function getPlatform(): string
    {
        return 'postgresql';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestData();
    }

    public function testCreateArticleWithComplexRelationships(): void
    {
        $articleId = Uuid::v4()->toString();
        $changes = new ChangeSet(
            attributes: [
                'title' => 'Complex Article with Relationships',
                'content' => 'This article demonstrates complex relationship handling.',
            ],
            relationships: [
                'author' => ['data' => ['type' => 'authors', 'id' => $this->author1->getId()]],
                'tags' => [
                    'data' => [
                        ['type' => 'tags', 'id' => $this->tag1->getId()],
                        ['type' => 'tags', 'id' => $this->tag2->getId()],
                    ]
                ],
            ]
        );

        $article = $this->validatingPersister->create('articles', $changes, $articleId);

        $this->assertInstanceOf(Article::class, $article);
        $this->assertSame('Complex Article with Relationships', $article->getTitle());
        $this->assertSame($this->author1->getId(), $article->getAuthor()->getId());
        $this->assertCount(2, $article->getTags());
        
        $tagIds = array_map(fn($tag) => $tag->getId(), $article->getTags()->toArray());
        $this->assertContains($this->tag1->getId(), $tagIds);
        $this->assertContains($this->tag2->getId(), $tagIds);
    }

    public function testUpdateArticleRelationshipsOnly(): void
    {
        // First create an article
        $article = $this->createTestArticle();
        $this->em->clear();

        // Update only relationships (similar to PATCH request from your example)
        $changes = new ChangeSet(
            attributes: [],
            relationships: [
                'author' => ['data' => ['type' => 'authors', 'id' => $this->author2->getId()]],
                'tags' => [
                    'data' => [
                        ['type' => 'tags', 'id' => $this->tag2->getId()],
                        ['type' => 'tags', 'id' => $this->tag3->getId()],
                    ]
                ],
            ]
        );

        $updatedArticle = $this->validatingPersister->update('articles', $article->getId(), $changes);

        $this->assertSame($this->author2->getId(), $updatedArticle->getAuthor()->getId());
        $this->assertCount(2, $updatedArticle->getTags());
        
        $tagIds = array_map(fn($tag) => $tag->getId(), $updatedArticle->getTags()->toArray());
        $this->assertContains($this->tag2->getId(), $tagIds);
        $this->assertContains($this->tag3->getId(), $tagIds);
        $this->assertNotContains($this->tag1->getId(), $tagIds);
    }

    public function testUpdateSingleRelationship(): void
    {
        $article = $this->createTestArticle();
        $this->em->clear();

        // Update only author relationship
        $changes = new ChangeSet(
            attributes: [],
            relationships: [
                'author' => ['data' => ['type' => 'authors', 'id' => $this->author2->getId()]],
            ]
        );

        $updatedArticle = $this->validatingPersister->update('articles', $article->getId(), $changes);

        $this->assertSame($this->author2->getId(), $updatedArticle->getAuthor()->getId());
        // Tags should remain unchanged
        $this->assertCount(2, $updatedArticle->getTags());
    }

    public function testClearToManyRelationship(): void
    {
        $article = $this->createTestArticle();
        $this->em->clear();

        // Clear tags by setting empty array
        $changes = new ChangeSet(
            attributes: [],
            relationships: [
                'tags' => ['data' => []],
            ]
        );

        $updatedArticle = $this->validatingPersister->update('articles', $article->getId(), $changes);

        $this->assertCount(0, $updatedArticle->getTags());
        // Author should remain unchanged
        $this->assertSame($this->author1->getId(), $updatedArticle->getAuthor()->getId());
    }

    public function testClearToOneRelationship(): void
    {
        $article = $this->createTestArticle();
        $this->em->clear();

        // Clear author by setting null
        $changes = new ChangeSet(
            attributes: [],
            relationships: [
                'author' => ['data' => null],
            ]
        );

        $updatedArticle = $this->validatingPersister->update('articles', $article->getId(), $changes);

        $this->assertNull($updatedArticle->getAuthor());
        // Tags should remain unchanged
        $this->assertCount(2, $updatedArticle->getTags());
    }

    public function testCreateWithAttributesAndRelationships(): void
    {
        $articleId = Uuid::v4()->toString();
        $changes = new ChangeSet(
            attributes: [
                'title' => 'Mixed Create Test',
                'content' => 'Testing both attributes and relationships.',
            ],
            relationships: [
                'author' => ['data' => ['type' => 'authors', 'id' => $this->author1->getId()]],
                'tags' => [
                    'data' => [
                        ['type' => 'tags', 'id' => $this->tag1->getId()],
                    ]
                ],
            ]
        );

        $article = $this->validatingPersister->create('articles', $changes, $articleId);

        $this->assertSame('Mixed Create Test', $article->getTitle());
        $this->assertSame('Testing both attributes and relationships.', $article->getContent());
        $this->assertSame($this->author1->getId(), $article->getAuthor()->getId());
        $this->assertCount(1, $article->getTags());
    }

    public function testUpdateWithAttributesAndRelationships(): void
    {
        $article = $this->createTestArticle();
        $this->em->clear();

        $changes = new ChangeSet(
            attributes: [
                'title' => 'Updated Title',
                'content' => 'Updated content with new relationships.',
            ],
            relationships: [
                'author' => ['data' => ['type' => 'authors', 'id' => $this->author2->getId()]],
                'tags' => [
                    'data' => [
                        ['type' => 'tags', 'id' => $this->tag3->getId()],
                    ]
                ],
            ]
        );

        $updatedArticle = $this->validatingPersister->update('articles', $article->getId(), $changes);

        $this->assertSame('Updated Title', $updatedArticle->getTitle());
        $this->assertSame('Updated content with new relationships.', $updatedArticle->getContent());
        $this->assertSame($this->author2->getId(), $updatedArticle->getAuthor()->getId());
        $this->assertCount(1, $updatedArticle->getTags());
        $this->assertSame($this->tag3->getId(), $updatedArticle->getTags()->first()->getId());
    }

    public function testInvalidRelationshipReference(): void
    {
        $changes = new ChangeSet(
            attributes: [
                'id' => Uuid::v4()->toString(),
                'title' => 'Test Article',
                'content' => 'Test content.',
            ],
            relationships: [
                'author' => ['data' => ['type' => 'authors', 'id' => 'non-existent-id']],
            ]
        );

        // Should throw ValidationException due to foreign key constraint violation
        $this->expectException(ValidationException::class);
        $this->validatingPersister->create('articles', $changes);
    }

    private function createTestData(): void
    {
        // Create authors
        $this->author1 = new Author();
        $this->author1->setId(Uuid::v4()->toString());
        $this->author1->setName('John Doe');
        $this->author1->setEmail('john@example.com');

        $this->author2 = new Author();
        $this->author2->setId(Uuid::v4()->toString());
        $this->author2->setName('Jane Smith');
        $this->author2->setEmail('jane@example.com');

        // Create tags
        $this->tag1 = new Tag();
        $this->tag1->setId(Uuid::v4()->toString());
        $this->tag1->setName('Technology');

        $this->tag2 = new Tag();
        $this->tag2->setId(Uuid::v4()->toString());
        $this->tag2->setName('Programming');

        $this->tag3 = new Tag();
        $this->tag3->setId(Uuid::v4()->toString());
        $this->tag3->setName('PHP');

        // Persist all test data
        $this->em->persist($this->author1);
        $this->em->persist($this->author2);
        $this->em->persist($this->tag1);
        $this->em->persist($this->tag2);
        $this->em->persist($this->tag3);
        $this->em->flush();
    }

    private function createTestArticle(): Article
    {
        $articleId = Uuid::v4()->toString();
        $changes = new ChangeSet(
            attributes: [
                'title' => 'Test Article',
                'content' => 'Test content for relationship testing.',
            ],
            relationships: [
                'author' => ['data' => ['type' => 'authors', 'id' => $this->author1->getId()]],
                'tags' => [
                    'data' => [
                        ['type' => 'tags', 'id' => $this->tag1->getId()],
                        ['type' => 'tags', 'id' => $this->tag2->getId()],
                    ]
                ],
            ]
        );

        return $this->validatingPersister->create('articles', $changes, $articleId);
    }
}
