<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Relationships;

use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Article;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\ArticleStatus;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Author;
use AlexFigures\Symfony\Tests\Integration\IntegrationTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Test that PATCH requests with relationships correctly load existing entities
 * instead of creating new instances with null IDs.
 *
 * This test reproduces the issue where bundle incorrectly deserializes relationships
 * during PATCH requests, creating new entity instances instead of loading existing ones.
 */
final class PatchRelationshipDenormalizationTest extends IntegrationTestCase
{
    /**
     * Test that PATCH request with to-one relationship loads existing entity.
     *
     * This test verifies that when updating an article with a new author relationship,
     * the bundle correctly loads the existing Author entity by ID instead of creating
     * a new Author instance with id=null.
     */
    public function testPatchWithToOneRelationshipLoadsExistingEntity(): void
    {
        // Create authors
        $author1 = new Author();
        $author1->setName('John Doe');
        $author1->setEmail('john@example.com');
        $this->em->persist($author1);

        $author2 = new Author();
        $author2->setName('Jane Smith');
        $author2->setEmail('jane@example.com');
        $this->em->persist($author2);

        // Create article with author1
        $article = new Article();
        $article->setTitle('Original Title');
        $article->setContent('Original content');
        $article->setAuthor($author1);
        $this->em->persist($article);

        $this->em->flush();
        $articleId = $article->getId();
        $author1Id = $author1->getId();
        $author2Id = $author2->getId();
        
        // Clear entity manager to ensure fresh load
        $this->em->clear();

        // PATCH request to update article with author2
        $payload = [
            'data' => [
                'type' => 'articles',
                'id' => $articleId,
                'attributes' => [
                    'title' => 'Updated Title',
                    'status' => ArticleStatus::DRAFT->value,
                ],
                'relationships' => [
                    'author' => [
                        'data' => ['type' => 'authors', 'id' => $author2Id],
                    ],
                ],
            ],
        ];

        $request = $this->createJsonApiRequest('PATCH', "/api/articles/{$articleId}", $payload);
        $response = $this->updateController()($request, 'articles', $articleId);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        // Verify that the relationship was updated correctly
        $document = $this->decode($response);
        self::assertSame('Updated Title', $document['data']['attributes']['title']);
        self::assertSame($author2Id, $document['data']['relationships']['author']['data']['id']);

        // Verify persistence - the article should have author2
        $this->em->clear();
        $updatedArticle = $this->em->find(Article::class, $articleId);
        self::assertNotNull($updatedArticle);
        self::assertSame('Updated Title', $updatedArticle->getTitle());
        self::assertNotNull($updatedArticle->getAuthor());
        self::assertSame($author2Id, $updatedArticle->getAuthor()->getId());
        
        // Verify that author1 still exists (wasn't replaced)
        $author1Check = $this->em->find(Author::class, $author1Id);
        self::assertNotNull($author1Check, 'Original author should still exist');
        
        // Verify that author2 exists and is the same instance
        $author2Check = $this->em->find(Author::class, $author2Id);
        self::assertNotNull($author2Check, 'New author should exist');
        self::assertSame($author2Check, $updatedArticle->getAuthor(), 'Article should reference the existing author2 entity');
    }

    /**
     * Test that PATCH request with only attributes doesn't affect relationships.
     *
     * This ensures that when updating only attributes, existing relationships
     * are preserved and not accidentally cleared or modified.
     */
    public function testPatchWithOnlyAttributesPreservesRelationships(): void
    {
        // Create author
        $author = new Author();
        $author->setName('John Doe');
        $author->setEmail('john@example.com');
        $this->em->persist($author);

        // Create article with author
        $article = new Article();
        $article->setTitle('Original Title');
        $article->setContent('Original content');
        $article->setAuthor($author);
        $this->em->persist($article);

        $this->em->flush();
        $articleId = $article->getId();
        $authorId = $author->getId();
        
        // Clear entity manager
        $this->em->clear();

        // PATCH request to update only attributes (no relationships)
        $payload = [
            'data' => [
                'type' => 'articles',
                'id' => $articleId,
                'attributes' => [
                    'title' => 'Updated Title',
                    'content' => 'Updated content',
                ],
            ],
        ];

        $request = $this->createJsonApiRequest('PATCH', "/api/articles/{$articleId}", $payload);
        $response = $this->updateController()($request, 'articles', $articleId);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        // Verify that the relationship was preserved
        $document = $this->decode($response);
        self::assertSame('Updated Title', $document['data']['attributes']['title']);
        self::assertSame($authorId, $document['data']['relationships']['author']['data']['id']);

        // Verify persistence
        $this->em->clear();
        $updatedArticle = $this->em->find(Article::class, $articleId);
        self::assertNotNull($updatedArticle);
        self::assertSame('Updated Title', $updatedArticle->getTitle());
        self::assertSame('Updated content', $updatedArticle->getContent());
        self::assertNotNull($updatedArticle->getAuthor());
        self::assertSame($authorId, $updatedArticle->getAuthor()->getId());
    }

    /**
     * Test that PATCH request with null relationship correctly clears it.
     *
     * This verifies that setting a relationship to null doesn't create
     * a new entity instance, but properly clears the relationship.
     */
    public function testPatchWithNullRelationshipClearsIt(): void
    {
        // Create author
        $author = new Author();
        $author->setName('John Doe');
        $author->setEmail('john@example.com');
        $this->em->persist($author);

        // Create article with author
        $article = new Article();
        $article->setTitle('Original Title');
        $article->setContent('Original content');
        $article->setAuthor($author);
        $this->em->persist($article);

        $this->em->flush();
        $articleId = $article->getId();
        $authorId = $author->getId();
        
        // Clear entity manager
        $this->em->clear();

        // PATCH request to clear the author relationship
        $payload = [
            'data' => [
                'type' => 'articles',
                'id' => $articleId,
                'relationships' => [
                    'author' => [
                        'data' => null,
                    ],
                ],
            ],
        ];

        $request = $this->createJsonApiRequest('PATCH', "/api/articles/{$articleId}", $payload);
        $response = $this->updateController()($request, 'articles', $articleId);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        // Verify that the relationship was cleared
        $document = $this->decode($response);
        self::assertNull($document['data']['relationships']['author']['data']);

        // Verify persistence
        $this->em->clear();
        $updatedArticle = $this->em->find(Article::class, $articleId);
        self::assertNotNull($updatedArticle);
        self::assertNull($updatedArticle->getAuthor());
        
        // Verify that the author still exists (wasn't deleted)
        $authorCheck = $this->em->find(Author::class, $authorId);
        self::assertNotNull($authorCheck, 'Author should still exist after relationship is cleared');
    }
}

