<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Integration\PostgreSQL;

use JsonApi\Symfony\Bridge\Doctrine\Relationship\GenericDoctrineRelationshipHandler;
use JsonApi\Symfony\Http\Exception\NotFoundException;
use JsonApi\Symfony\Tests\Integration\DoctrineIntegrationTestCase;
use JsonApi\Symfony\Tests\Integration\Fixtures\Entity\Article;
use JsonApi\Symfony\Tests\Integration\Fixtures\Entity\Author;
use JsonApi\Symfony\Tests\Integration\Fixtures\Entity\Tag;

final class RelationshipHandlerTest extends DoctrineIntegrationTestCase
{
    private GenericDoctrineRelationshipHandler $handler;

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

        $this->handler = new GenericDoctrineRelationshipHandler(
            $this->em,
            $this->registry,
            $this->accessor,
        );
    }

    // ==================== RelationshipReader Tests ====================

    public function testGetToOneIdReturnsAuthorId(): void
    {
        $this->seedDatabase();

        $article = $this->em->find(Article::class, 'article-1');
        $authorId = $this->handler->getToOneId($article, 'author');

        self::assertSame('author-1', $authorId);
    }

    public function testGetToOneIdReturnsNullWhenNoRelation(): void
    {
        $this->seedDatabase();

        $article = new Article();
        $article->setId('article-3');
        $article->setTitle('No Author');
        $article->setContent('Content');
        $this->em->persist($article);
        $this->em->flush();
        $this->em->clear();

        $article = $this->em->find(Article::class, 'article-3');
        $authorId = $this->handler->getToOneId($article, 'author');

        self::assertNull($authorId);
    }

    public function testGetToManyIdsReturnsTagIds(): void
    {
        $this->seedDatabase();

        $article = $this->em->find(Article::class, 'article-1');
        $tagIds = $this->handler->getToManyIds($article, 'tags');

        self::assertCount(2, $tagIds);
        self::assertContains('tag-1', $tagIds);
        self::assertContains('tag-2', $tagIds);
    }

    public function testGetToManyIdsReturnsEmptyArrayWhenNoRelations(): void
    {
        $this->seedDatabase();

        $article = new Article();
        $article->setId('article-3');
        $article->setTitle('No Tags');
        $article->setContent('Content');
        $this->em->persist($article);
        $this->em->flush();
        $this->em->clear();

        $article = $this->em->find(Article::class, 'article-3');
        $tagIds = $this->handler->getToManyIds($article, 'tags');

        self::assertCount(0, $tagIds);
        self::assertSame([], iterator_to_array($tagIds));
    }

    public function testGetRelatedResourceReturnsAuthor(): void
    {
        $this->seedDatabase();

        $article = $this->em->find(Article::class, 'article-1');
        $author = $this->handler->getRelatedResource($article, 'author');

        self::assertInstanceOf(Author::class, $author);
        self::assertSame('author-1', $author->getId());
        self::assertSame('John Doe', $author->getName());
    }

    public function testGetRelatedCollectionReturnsTags(): void
    {
        $this->seedDatabase();

        $article = $this->em->find(Article::class, 'article-1');
        $tags = $this->handler->getRelatedCollection($article, 'tags');

        self::assertCount(2, $tags);
        self::assertContainsOnlyInstancesOf(Tag::class, $tags);
    }

    // ==================== RelationshipUpdater Tests ====================

    public function testReplaceToOneUpdatesAuthor(): void
    {
        $this->seedDatabase();

        $article = $this->em->find(Article::class, 'article-1');
        self::assertSame('author-1', $article->getAuthor()->getId());

        // Change author
        $this->handler->replaceToOne($article, 'author', 'author-2');

        // Check
        $this->em->clear();
        $article = $this->em->find(Article::class, 'article-1');
        self::assertSame('author-2', $article->getAuthor()->getId());
    }

    public function testReplaceToOneWithNullRemovesRelation(): void
    {
        $this->seedDatabase();

        $article = $this->em->find(Article::class, 'article-1');
        self::assertNotNull($article->getAuthor());

        // Remove author
        $this->handler->replaceToOne($article, 'author', null);

        // Verify
        $this->em->clear();
        $article = $this->em->find(Article::class, 'article-1');
        self::assertNull($article->getAuthor());
    }

    public function testReplaceToOneThrowsNotFoundForInvalidId(): void
    {
        $this->seedDatabase();

        $article = $this->em->find(Article::class, 'article-1');

        $this->expectException(NotFoundException::class);
        $this->handler->replaceToOne($article, 'author', 'non-existent');
    }

    public function testReplaceToManyReplacesAllTags(): void
    {
        $this->seedDatabase();

        $article = $this->em->find(Article::class, 'article-1');
        self::assertCount(2, $article->getTags());

        // Replace with one tag
        $this->handler->replaceToMany($article, 'tags', ['tag-1']);

        // Verify
        $this->em->clear();
        $article = $this->em->find(Article::class, 'article-1');
        self::assertCount(1, $article->getTags());
        self::assertSame('tag-1', $article->getTags()->first()->getId());
    }

    public function testReplaceToManyWithEmptyArrayClearsRelations(): void
    {
        $this->seedDatabase();

        $article = $this->em->find(Article::class, 'article-1');
        self::assertCount(2, $article->getTags());

        // Clear all tags
        $this->handler->replaceToMany($article, 'tags', []);

        // Verify
        $this->em->clear();
        $article = $this->em->find(Article::class, 'article-1');
        self::assertCount(0, $article->getTags());
    }

    public function testAddToManyAddsNewTags(): void
    {
        $this->seedDatabase();

        // Create new tag
        $tag3 = new Tag();
        $tag3->setId('tag-3');
        $tag3->setName('Doctrine');
        $this->em->persist($tag3);
        $this->em->flush();
        $this->em->clear();

        $article = $this->em->find(Article::class, 'article-2');
        self::assertCount(1, $article->getTags()); // Only tag-1

        // Add tag-2 and tag-3
        $this->handler->addToMany($article, 'tags', ['tag-2', 'tag-3']);

        // Verify
        $this->em->clear();
        $article = $this->em->find(Article::class, 'article-2');
        self::assertCount(3, $article->getTags());
    }

    public function testAddToManyDoesNotAddDuplicates(): void
    {
        $this->seedDatabase();

        $article = $this->em->find(Article::class, 'article-1');
        self::assertCount(2, $article->getTags());

        // Try to add already existing tag
        $this->handler->addToMany($article, 'tags', ['tag-1']);

        // Verify - count unchanged
        $this->em->clear();
        $article = $this->em->find(Article::class, 'article-1');
        self::assertCount(2, $article->getTags());
    }

    public function testRemoveFromToManyRemovesTags(): void
    {
        $this->seedDatabase();

        $article = $this->em->find(Article::class, 'article-1');
        self::assertCount(2, $article->getTags());

        // Remove one tag
        $this->handler->removeFromToMany($article, 'tags', ['tag-1']);

        // Verify
        $this->em->clear();
        $article = $this->em->find(Article::class, 'article-1');
        self::assertCount(1, $article->getTags());
        self::assertSame('tag-2', $article->getTags()->first()->getId());
    }

    public function testRemoveFromToManyIgnoresNonExistentTags(): void
    {
        $this->seedDatabase();

        $article = $this->em->find(Article::class, 'article-1');
        self::assertCount(2, $article->getTags());

        // Create new tag, but do NOT add it to article
        $tag3 = new Tag();
        $tag3->setId('tag-3');
        $tag3->setName('Doctrine');
        $this->em->persist($tag3);
        $this->em->flush();
        $this->em->clear();

        $article = $this->em->find(Article::class, 'article-1');

        // Try to remove tag that is not in collection
        $this->handler->removeFromToMany($article, 'tags', ['tag-3']);

        // Verify - count unchanged
        $this->em->clear();
        $article = $this->em->find(Article::class, 'article-1');
        self::assertCount(2, $article->getTags());
    }
}
