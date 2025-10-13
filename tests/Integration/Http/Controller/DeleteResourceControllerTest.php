<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Http\Controller;

use AlexFigures\Symfony\Http\Controller\DeleteResourceController;
use AlexFigures\Symfony\Tests\Integration\DoctrineIntegrationTestCase;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Article;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Author;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Tag;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Response;

/**
 * Integration tests for DeleteResourceController.
 *
 * Tests DELETE /api/{type}/{id} endpoint with real PostgreSQL database.
 */
final class DeleteResourceControllerTest extends DoctrineIntegrationTestCase
{
    private DeleteResourceController $controller;

    protected function getDatabaseUrl(): string
    {
        // In Docker: postgres:5432, locally: localhost:5432
        return $_ENV['DATABASE_URL_POSTGRES'] ?? 'postgresql://jsonapi:secret@postgres:5432/jsonapi_test?serverVersion=16&charset=utf8';
    }

    protected function getPlatform(): string
    {
        return 'postgresql';
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Set up EventDispatcher
        $eventDispatcher = new EventDispatcher();

        // Create DeleteResourceController
        $this->controller = new DeleteResourceController(
            $this->registry,
            $this->validatingProcessor,
            $this->transactionManager,
            $eventDispatcher
        );
    }

    /**
     * Test 1: Delete simple resource (no relationships).
     *
     * Validates:
     * - 204 No Content status
     * - Resource removed from database
     * - No response body
     */
    public function testDeleteSimpleResource(): void
    {
        // Create tag
        $tag = new Tag();
        $tag->setName('PHP');
        $this->em->persist($tag);
        $this->em->flush();

        $tagId = $tag->getId();
        $this->em->clear();

        // Verify tag exists
        $existingTag = $this->em->find(Tag::class, $tagId);
        self::assertNotNull($existingTag);

        // Delete tag
        $response = ($this->controller)('tags', $tagId);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertEmpty($response->getContent());

        // Verify tag deleted
        $this->em->clear();
        $deletedTag = $this->em->find(Tag::class, $tagId);
        self::assertNull($deletedTag);
    }

    /**
     * Test 2: Delete resource with to-one relationship.
     *
     * Validates:
     * - Resource with relationship can be deleted
     * - Related resource remains (no cascade delete)
     */
    public function testDeleteResourceWithToOneRelationship(): void
    {
        // Create author
        $author = new Author();
        $author->setName('John Doe');
        $author->setEmail('john@example.com');
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

        // Delete article
        $response = ($this->controller)('articles', $articleId);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        // Verify article deleted
        $this->em->clear();
        $deletedArticle = $this->em->find(Article::class, $articleId);
        self::assertNull($deletedArticle);

        // Verify author still exists
        $existingAuthor = $this->em->find(Author::class, $authorId);
        self::assertNotNull($existingAuthor);
        self::assertSame('John Doe', $existingAuthor->getName());
    }

    /**
     * Test 3: Delete resource with to-many relationship.
     *
     * Validates:
     * - Resource with multiple relationships can be deleted
     * - Related resources remain (no cascade delete)
     */
    public function testDeleteResourceWithToManyRelationship(): void
    {
        // Create tags
        $tag1 = new Tag();
        $tag1->setName('PHP');
        $this->em->persist($tag1);

        $tag2 = new Tag();
        $tag2->setName('Symfony');
        $this->em->persist($tag2);

        // Create author
        $author = new Author();
        $author->setName('John Doe');
        $author->setEmail('john@example.com');
        $this->em->persist($author);

        // Create article with tags
        $article = new Article();
        $article->setTitle('Test Article');
        $article->setContent('Content');
        $article->setAuthor($author);
        $article->addTag($tag1);
        $article->addTag($tag2);
        $this->em->persist($article);

        $this->em->flush();
        $articleId = $article->getId();
        $tag1Id = $tag1->getId();
        $tag2Id = $tag2->getId();
        $this->em->clear();

        // Delete article
        $response = ($this->controller)('articles', $articleId);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        // Verify article deleted
        $this->em->clear();
        $deletedArticle = $this->em->find(Article::class, $articleId);
        self::assertNull($deletedArticle);

        // Verify tags still exist
        $existingTag1 = $this->em->find(Tag::class, $tag1Id);
        $existingTag2 = $this->em->find(Tag::class, $tag2Id);
        self::assertNotNull($existingTag1);
        self::assertNotNull($existingTag2);
        self::assertSame('PHP', $existingTag1->getName());
        self::assertSame('Symfony', $existingTag2->getName());
    }

    /**
     * Test 4: Error - resource not found (404).
     *
     * Validates:
     * - 404 status when resource doesn't exist
     */
    public function testErrorResourceNotFound(): void
    {
        $nonExistentId = '00000000-0000-0000-0000-000000000000';

        $this->expectException(\AlexFigures\Symfony\Http\Exception\NotFoundException::class);

        ($this->controller)('tags', $nonExistentId);
    }

    /**
     * Test 5: Error - unknown resource type (404).
     *
     * Validates:
     * - 404 status for unknown type
     */
    public function testErrorUnknownResourceType(): void
    {
        $this->expectException(\AlexFigures\Symfony\Http\Exception\NotFoundException::class);
        $this->expectExceptionMessage('Resource type "unknown-type" not found');

        ($this->controller)('unknown-type', 'some-id');
    }

    /**
     * Test 6: Error - delete resource that is referenced by another resource.
     *
     * Validates:
     * - Foreign key constraint prevents deletion
     * - Database integrity is maintained
     */
    public function testErrorDeleteResourceReferencedByOthers(): void
    {
        // Create author
        $author = new Author();
        $author->setName('John Doe');
        $author->setEmail('john@example.com');
        $this->em->persist($author);

        // Create article referencing author
        $article = new Article();
        $article->setTitle('Test Article');
        $article->setContent('Content');
        $article->setAuthor($author);
        $this->em->persist($article);

        $this->em->flush();
        $authorId = $author->getId();
        $this->em->clear();

        // Attempt to delete author should fail due to foreign key constraint
        $this->expectException(\Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException::class);

        ($this->controller)('authors', $authorId);
    }

    /**
     * Test 7: Multiple deletes of same resource.
     *
     * Validates:
     * - Second delete returns 404
     */
    public function testMultipleDeletesOfSameResource(): void
    {
        // Create tag
        $tag = new Tag();
        $tag->setName('PHP');
        $this->em->persist($tag);
        $this->em->flush();

        $tagId = $tag->getId();
        $this->em->clear();

        // First delete succeeds
        $response = ($this->controller)('tags', $tagId);
        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        // Second delete fails with 404
        $this->expectException(\AlexFigures\Symfony\Http\Exception\NotFoundException::class);

        ($this->controller)('tags', $tagId);
    }
}
