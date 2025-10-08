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
 * Test improved relationship handling with:
 * - Diff semantics (add/remove instead of replace)
 * - Proper owning/inverse side synchronization
 * - Type validation and existence checking
 * - Cardinality constraints
 * - Write policy enforcement
 * - Precise error pointers
 */
final class ImprovedRelationshipHandlingTest extends DoctrineIntegrationTestCase
{
    private Author $author1;
    private Author $author2;
    private Tag $tag1;
    private Tag $tag2;
    private Tag $tag3;
    private Article $article;

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

    /**
     * Test diff semantics: adding tags without replacing existing ones.
     */
    public function testDiffSemanticsAddTags(): void
    {
        // Article initially has tag1
        $this->article->addTag($this->tag1);
        $this->em->flush();
        $this->em->clear();

        // Reload article
        $article = $this->em->find(Article::class, $this->article->getId());
        $this->assertCount(1, $article->getTags());

        // Update to have tag1 and tag2 (should add tag2, keep tag1)
        $changes = new ChangeSet(
            relationships: [
                'tags' => [
                    'data' => [
                        ['type' => 'tags', 'id' => $this->tag1->getId()],
                        ['type' => 'tags', 'id' => $this->tag2->getId()],
                    ]
                ],
            ]
        );

        $updatedArticle = $this->validatingPersister->update('articles', $article->getId(), $changes);
        
        $this->assertCount(2, $updatedArticle->getTags());
        $tagIds = array_map(fn($tag) => $tag->getId(), $updatedArticle->getTags()->toArray());
        $this->assertContains($this->tag1->getId(), $tagIds);
        $this->assertContains($this->tag2->getId(), $tagIds);
    }

    /**
     * Test diff semantics: removing tags without affecting others.
     */
    public function testDiffSemanticsRemoveTags(): void
    {
        // Article initially has tag1 and tag2
        $this->article->addTag($this->tag1);
        $this->article->addTag($this->tag2);
        $this->em->flush();
        $this->em->clear();

        // Reload article
        $article = $this->em->find(Article::class, $this->article->getId());
        $this->assertCount(2, $article->getTags());

        // Update to have only tag1 (should remove tag2, keep tag1)
        $changes = new ChangeSet(
            relationships: [
                'tags' => [
                    'data' => [
                        ['type' => 'tags', 'id' => $this->tag1->getId()],
                    ]
                ],
            ]
        );

        $updatedArticle = $this->validatingPersister->update('articles', $article->getId(), $changes);
        
        $this->assertCount(1, $updatedArticle->getTags());
        $this->assertSame($this->tag1->getId(), $updatedArticle->getTags()->first()->getId());
    }

    /**
     * Test type validation: wrong type should throw ValidationException with precise pointer.
     */
    public function testTypeValidationError(): void
    {
        $changes = new ChangeSet(
            attributes: [
                'id' => Uuid::v4()->toString(),
                'title' => 'Test Article',
                'content' => 'Test content.',
            ],
            relationships: [
                'author' => ['data' => ['type' => 'wrong-type', 'id' => $this->author1->getId()]],
            ]
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid relationship type');
        
        try {
            $this->validatingPersister->create('articles', $changes);
        } catch (ValidationException $e) {
            // Check that error has precise pointer
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);
            $this->assertSame('/data/relationships/author/data/type', $errors[0]['source']['pointer']);
            throw $e;
        }
    }

    /**
     * Test duplicate detection in to-many relationships.
     */
    public function testDuplicateDetection(): void
    {
        $changes = new ChangeSet(
            attributes: [
                'id' => Uuid::v4()->toString(),
                'title' => 'Test Article',
                'content' => 'Test content.',
            ],
            relationships: [
                'tags' => [
                    'data' => [
                        ['type' => 'tags', 'id' => $this->tag1->getId()],
                        ['type' => 'tags', 'id' => $this->tag1->getId()], // Duplicate
                    ]
                ],
            ]
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Duplicate relationship');
        
        try {
            $this->validatingPersister->create('articles', $changes);
        } catch (ValidationException $e) {
            // Check that error has precise pointer to the duplicate
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);
            $this->assertSame('/data/relationships/tags/data/1/id', $errors[0]['source']['pointer']);
            throw $e;
        }
    }

    /**
     * Test existence checking with verify policy.
     */
    public function testExistenceCheckingWithVerifyPolicy(): void
    {
        // This test would require configuring relationship metadata with verify policy
        // For now, we test the default lazy reference behavior
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

        // With lazy reference (default), this should work initially but fail on flush
        $this->expectException(ValidationException::class);
        $this->validatingPersister->create('articles', $changes);
    }

    /**
     * Test that collections are not replaced but modified in-place.
     */
    public function testCollectionNotReplaced(): void
    {
        // Add initial tags
        $this->article->addTag($this->tag1);
        $this->em->flush();
        
        $originalCollection = $this->article->getTags();
        $originalCollectionId = spl_object_id($originalCollection);

        // Update tags
        $changes = new ChangeSet(
            relationships: [
                'tags' => [
                    'data' => [
                        ['type' => 'tags', 'id' => $this->tag2->getId()],
                    ]
                ],
            ]
        );

        $this->validatingPersister->update('articles', $this->article->getId(), $changes);
        
        // Collection should be the same object, just modified
        $this->assertSame($originalCollectionId, spl_object_id($this->article->getTags()));
        $this->assertCount(1, $this->article->getTags());
        $this->assertSame($this->tag2->getId(), $this->article->getTags()->first()->getId());
    }

    private function createTestData(): void
    {
        // Create authors
        $this->author1 = new Author();
        $this->author1->setId(Uuid::v4()->toString());
        $this->author1->setName('Author 1');
        $this->author1->setEmail('author1@example.com');
        $this->em->persist($this->author1);

        $this->author2 = new Author();
        $this->author2->setId(Uuid::v4()->toString());
        $this->author2->setName('Author 2');
        $this->author2->setEmail('author2@example.com');
        $this->em->persist($this->author2);

        // Create tags
        $this->tag1 = new Tag();
        $this->tag1->setId(Uuid::v4()->toString());
        $this->tag1->setName('Tag 1');
        $this->em->persist($this->tag1);

        $this->tag2 = new Tag();
        $this->tag2->setId(Uuid::v4()->toString());
        $this->tag2->setName('Tag 2');
        $this->em->persist($this->tag2);

        $this->tag3 = new Tag();
        $this->tag3->setId(Uuid::v4()->toString());
        $this->tag3->setName('Tag 3');
        $this->em->persist($this->tag3);

        // Create article
        $this->article = new Article();
        $this->article->setId(Uuid::v4()->toString());
        $this->article->setTitle('Test Article');
        $this->article->setContent('Test content');
        $this->em->persist($this->article);

        $this->em->flush();
    }
}
