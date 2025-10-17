<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Bridge\Doctrine\Relationship;

use AlexFigures\Symfony\Bridge\Doctrine\Relationship\GenericDoctrineRelationshipHandler;
use AlexFigures\Symfony\Contract\Data\RelationshipReader;
use AlexFigures\Symfony\Contract\Data\RelationshipUpdater;
use AlexFigures\Symfony\Contract\Data\ResourceIdentifier;
use AlexFigures\Symfony\Tests\Integration\DoctrineIntegrationTestCase;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Article;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Author;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Category;

/**
 * Integration test for GenericDoctrineRelationshipHandler.
 *
 * Validates that the handler correctly implements both RelationshipReader
 * and RelationshipUpdater interfaces and can handle relationship operations.
 */
final class GenericDoctrineRelationshipHandlerTest extends DoctrineIntegrationTestCase
{
    private GenericDoctrineRelationshipHandler $handler;

    protected function getDatabaseUrl(): string
    {
        return $_ENV['DATABASE_URL_POSTGRES'] ?? 'postgresql://jsonapi:secret@postgres:5432/jsonapi_test?serverVersion=16&charset=utf8';
    }

    protected function getPlatform(): string
    {
        return 'postgresql';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = new GenericDoctrineRelationshipHandler(
            em: $this->em,
            registry: $this->registry,
            accessor: $this->accessor,
        );
    }

    /**
     * Test 1: Handler implements both RelationshipReader and RelationshipUpdater.
     */
    public function testHandlerImplementsBothInterfaces(): void
    {
        self::assertInstanceOf(RelationshipReader::class, $this->handler);
        self::assertInstanceOf(RelationshipUpdater::class, $this->handler);
    }

    /**
     * Test 2: replaceToOne() can set a to-one relationship.
     */
    public function testReplaceToOneCanSetRelationship(): void
    {
        // Create author
        $author = new Author();
        $author->setName('John Doe');
        $author->setEmail('john@example.com');
        $this->em->persist($author);

        // Create article without author
        $article = new Article();
        $article->setTitle('Test Article');
        $article->setContent('Content');
        $this->em->persist($article);

        $this->em->flush();
        $articleId = $article->getId();
        $authorId = $author->getId();
        $this->em->clear();

        // Use RelationshipUpdater to set author
        $target = new ResourceIdentifier(type: 'authors', id: $authorId);
        $this->handler->replaceToOne(type: 'articles', idOrRel: $articleId, relOrTarget: 'author', target: $target);

        // Verify in database
        $this->em->clear();
        $updatedArticle = $this->em->find(Article::class, $articleId);
        self::assertNotNull($updatedArticle);
        self::assertNotNull($updatedArticle->getAuthor());
        self::assertSame($authorId, $updatedArticle->getAuthor()->getId());
    }

    /**
     * Test 3: replaceToOne() can clear a to-one relationship (set to null).
     *
     * This is the critical test for the issue you're experiencing.
     */
    public function testReplaceToOneCanClearRelationship(): void
    {
        // Create author
        $author = new Author();
        $author->setName('Jane Doe');
        $author->setEmail('jane@example.com');
        $this->em->persist($author);

        // Create article WITH author
        $article = new Article();
        $article->setTitle('Test Article');
        $article->setContent('Content');
        $article->setAuthor($author);
        $this->em->persist($article);

        $this->em->flush();
        $articleId = $article->getId();
        $this->em->clear();

        // Verify initial state
        $initialArticle = $this->em->find(Article::class, $articleId);
        self::assertNotNull($initialArticle);
        self::assertNotNull($initialArticle->getAuthor(), 'Article should have author before clearing');
        $this->em->clear();

        // Use RelationshipUpdater to CLEAR author (set to null)
        $this->handler->replaceToOne(type: 'articles', idOrRel: $articleId, relOrTarget: 'author', target: null);

        // Verify in database - author should be null
        $this->em->clear();
        $updatedArticle = $this->em->find(Article::class, $articleId);
        self::assertNotNull($updatedArticle, 'Article should still exist');
        self::assertNull($updatedArticle->getAuthor(), 'Article author should be null after clearing');
    }

    /**
     * Test 4: replaceToOne() can clear self-referential relationship (Category parent).
     *
     * This tests the specific scenario from your UpdateResourceControllerTest.
     */
    public function testReplaceToOneCanClearSelfReferentialRelationship(): void
    {
        // Create parent category
        $parent = new Category();
        $parent->setName('Parent');
        $parent->setSortOrder(1);
        $this->em->persist($parent);

        // Create child category WITH parent
        $child = new Category();
        $child->setName('Child');
        $child->setSortOrder(2);
        $child->setParent($parent);
        $this->em->persist($child);

        $this->em->flush();
        $childId = $child->getId();
        $parentId = $parent->getId();
        $this->em->clear();

        // Verify initial state
        $initialChild = $this->em->find(Category::class, $childId);
        self::assertNotNull($initialChild);
        self::assertNotNull($initialChild->getParent(), 'Child should have parent before clearing');
        self::assertSame($parentId, $initialChild->getParent()->getId());
        $this->em->clear();

        // Use RelationshipUpdater to CLEAR parent (set to null)
        $this->handler->replaceToOne(type: 'categories', idOrRel: $childId, relOrTarget: 'parent', target: null);

        // Verify in database - parent should be null
        $this->em->clear();
        $updatedChild = $this->em->find(Category::class, $childId);
        self::assertNotNull($updatedChild, 'Child category should still exist');
        self::assertNull($updatedChild->getParent(), 'Child category parent should be null after clearing');

        // Verify parent still exists and is unchanged
        $parentCategory = $this->em->find(Category::class, $parentId);
        self::assertNotNull($parentCategory, 'Parent category should still exist');
        self::assertSame('Parent', $parentCategory->getName());
    }

    /**
     * Test 5: getToOneId() can read a to-one relationship.
     */
    public function testGetToOneIdCanReadRelationship(): void
    {
        // Create author
        $author = new Author();
        $author->setName('Bob Smith');
        $author->setEmail('bob@example.com');
        $this->em->persist($author);

        // Create article with author
        $article = new Article();
        $article->setTitle('Test Article');
        $article->setContent('Content');
        $article->setAuthor($author);
        $this->em->persist($article);

        $this->em->flush();
        $articleId = $article->getId();
        $authorId = $author->getId();
        $this->em->clear();

        // Use RelationshipReader to get author ID
        $readAuthorId = $this->handler->getToOneId(type: 'articles', idOrRel: $articleId, rel: 'author');

        self::assertSame($authorId, $readAuthorId);
    }

    /**
     * Test 6: getToOneId() returns null for cleared relationship.
     */
    public function testGetToOneIdReturnsNullForClearedRelationship(): void
    {
        // Create article without author
        $article = new Article();
        $article->setTitle('Test Article');
        $article->setContent('Content');
        $this->em->persist($article);

        $this->em->flush();
        $articleId = $article->getId();
        $this->em->clear();

        // Use RelationshipReader to get author ID (should be null)
        $authorId = $this->handler->getToOneId(type: 'articles', idOrRel: $articleId, rel: 'author');

        self::assertNull($authorId, 'Author ID should be null for article without author');
    }
}

