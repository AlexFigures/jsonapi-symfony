<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Http\Controller;

use AlexFigures\Symfony\Bridge\Doctrine\ExistenceChecker\DoctrineExistenceChecker;
use AlexFigures\Symfony\Bridge\Doctrine\Relationship\GenericDoctrineRelationshipHandler;
use AlexFigures\Symfony\Bridge\Doctrine\Transaction\DoctrineTransactionManager;
use AlexFigures\Symfony\Http\Controller\RelationshipWriteController;
use AlexFigures\Symfony\Http\Error\ErrorBuilder;
use AlexFigures\Symfony\Http\Error\ErrorMapper;
use AlexFigures\Symfony\Http\Negotiation\MediaType;
use AlexFigures\Symfony\Http\Relationship\LinkageBuilder;
use AlexFigures\Symfony\Http\Relationship\WriteRelationshipsResponseConfig;
use AlexFigures\Symfony\Http\Write\RelationshipDocumentValidator;
use AlexFigures\Symfony\Tests\Integration\DoctrineIntegrationTestCase;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Article;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Author;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Tag;
use AlexFigures\Symfony\Tests\Util\JsonApiResponseAsserts;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Integration tests for RelationshipWriteController.
 *
 * Tests PATCH/POST/DELETE /api/{type}/{id}/relationships/{rel} endpoints with real PostgreSQL database.
 */
final class RelationshipWriteControllerTest extends DoctrineIntegrationTestCase
{
    use JsonApiResponseAsserts;

    private RelationshipWriteController $controller;

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

        $errorBuilder = new ErrorBuilder(true);
        $errorMapper = new ErrorMapper($errorBuilder);

        $relationshipHandler = new GenericDoctrineRelationshipHandler(
            $this->managerRegistry,
            $this->registry,
            $this->accessor,
            $this->flushManager
        );

        $paginationConfig = new \AlexFigures\Symfony\Http\Request\PaginationConfig(defaultSize: 10, maxSize: 100);

        $linkageBuilder = new LinkageBuilder(
            $this->registry,
            $relationshipHandler,
            $paginationConfig
        );

        $existenceChecker = new DoctrineExistenceChecker($this->managerRegistry, $this->registry);

        $validator = new RelationshipDocumentValidator(
            $this->registry,
            $existenceChecker,
            $errorMapper
        );

        $transactionManager = new DoctrineTransactionManager($this->managerRegistry, $this->flushManager);
        $eventDispatcher = new EventDispatcher();

        $responseConfig = new WriteRelationshipsResponseConfig('204');

        $this->controller = new RelationshipWriteController(
            $validator,
            $relationshipHandler,
            $linkageBuilder,
            $responseConfig,
            $errorMapper,
            $transactionManager,
            $eventDispatcher
        );
    }

    /**
     * Test 1: PATCH - Replace to-one relationship.
     */
    public function testPatchReplaceToOneRelationship(): void
    {
        $author1 = new Author();
        $author1->setName('John Doe');
        $author1->setEmail('john@example.com');
        $this->em->persist($author1);

        $author2 = new Author();
        $author2->setName('Jane Smith');
        $author2->setEmail('jane@example.com');
        $this->em->persist($author2);

        $article = new Article();
        $article->setTitle('Test Article');
        $article->setContent('Content');
        $article->setAuthor($author1);
        $this->em->persist($article);

        $this->em->flush();
        $articleId = $article->getId();
        $author2Id = $author2->getId();
        $this->em->clear();

        $payload = json_encode([
            'data' => [
                'type' => 'authors',
                'id' => $author2Id,
            ],
        ]);

        $request = Request::create(
            "/api/articles/{$articleId}/relationships/author",
            'PATCH',
            [],
            [],
            [],
            ['CONTENT_TYPE' => MediaType::JSON_API],
            $payload
        );

        $response = ($this->controller)($request, 'articles', $articleId, 'author');
        $this->flush();

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        // Verify in database
        $this->em->clear();
        $updatedArticle = $this->em->find(Article::class, $articleId);
        self::assertNotNull($updatedArticle);
        self::assertSame($author2Id, $updatedArticle->getAuthor()->getId());
    }

    /**
     * Test 2: PATCH - Clear to-one relationship (set to null).
     */
    public function testPatchClearToOneRelationship(): void
    {
        $author = new Author();
        $author->setName('John Doe');
        $author->setEmail('john@example.com');
        $this->em->persist($author);

        $article = new Article();
        $article->setTitle('Test Article');
        $article->setContent('Content');
        $article->setAuthor($author);
        $this->em->persist($article);

        $this->em->flush();
        $articleId = $article->getId();
        $this->em->clear();

        $payload = json_encode(['data' => null]);

        $request = Request::create(
            "/api/articles/{$articleId}/relationships/author",
            'PATCH',
            [],
            [],
            [],
            ['CONTENT_TYPE' => MediaType::JSON_API],
            $payload
        );

        $response = ($this->controller)($request, 'articles', $articleId, 'author');
        $this->flush();

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        // Verify in database
        $this->em->clear();
        $updatedArticle = $this->em->find(Article::class, $articleId);
        self::assertNotNull($updatedArticle);
        self::assertNull($updatedArticle->getAuthor());
    }

    /**
     * Test 3: PATCH - Replace to-many relationship.
     */
    public function testPatchReplaceToManyRelationship(): void
    {
        $author = new Author();
        $author->setName('John Doe');
        $author->setEmail('john@example.com');
        $this->em->persist($author);

        $tag1 = new Tag();
        $tag1->setName('PHP');
        $this->em->persist($tag1);

        $tag2 = new Tag();
        $tag2->setName('Symfony');
        $this->em->persist($tag2);

        $tag3 = new Tag();
        $tag3->setName('Doctrine');
        $this->em->persist($tag3);

        $article = new Article();
        $article->setTitle('Test Article');
        $article->setContent('Content');
        $article->setAuthor($author);
        $article->addTag($tag1);
        $this->em->persist($article);

        $this->em->flush();
        $articleId = $article->getId();
        $tag2Id = $tag2->getId();
        $tag3Id = $tag3->getId();
        $this->em->clear();

        $payload = json_encode([
            'data' => [
                ['type' => 'tags', 'id' => $tag2Id],
                ['type' => 'tags', 'id' => $tag3Id],
            ],
        ]);

        $request = Request::create(
            "/api/articles/{$articleId}/relationships/tags",
            'PATCH',
            [],
            [],
            [],
            ['CONTENT_TYPE' => MediaType::JSON_API],
            $payload
        );

        $response = ($this->controller)($request, 'articles', $articleId, 'tags');
        $this->flush();

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        // Verify in database
        $this->em->clear();
        $updatedArticle = $this->em->find(Article::class, $articleId);
        self::assertNotNull($updatedArticle);
        self::assertCount(2, $updatedArticle->getTags());
    }

    /**
     * Test 4: POST - Add to to-many relationship.
     */
    public function testPostAddToToManyRelationship(): void
    {
        $author = new Author();
        $author->setName('John Doe');
        $author->setEmail('john@example.com');
        $this->em->persist($author);

        $tag1 = new Tag();
        $tag1->setName('PHP');
        $this->em->persist($tag1);

        $tag2 = new Tag();
        $tag2->setName('Symfony');
        $this->em->persist($tag2);

        $article = new Article();
        $article->setTitle('Test Article');
        $article->setContent('Content');
        $article->setAuthor($author);
        $article->addTag($tag1);
        $this->em->persist($article);

        $this->em->flush();
        $articleId = $article->getId();
        $tag2Id = $tag2->getId();
        $this->em->clear();

        $payload = json_encode([
            'data' => [
                ['type' => 'tags', 'id' => $tag2Id],
            ],
        ]);

        $request = Request::create(
            "/api/articles/{$articleId}/relationships/tags",
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => MediaType::JSON_API],
            $payload
        );

        $response = ($this->controller)($request, 'articles', $articleId, 'tags');
        $this->flush();

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        // Verify in database
        $this->em->clear();
        $updatedArticle = $this->em->find(Article::class, $articleId);
        self::assertNotNull($updatedArticle);
        self::assertCount(2, $updatedArticle->getTags());
    }

    /**
     * Test 5: DELETE - Remove from to-many relationship.
     */
    public function testDeleteRemoveFromToManyRelationship(): void
    {
        $author = new Author();
        $author->setName('John Doe');
        $author->setEmail('john@example.com');
        $this->em->persist($author);

        $tag1 = new Tag();
        $tag1->setName('PHP');
        $this->em->persist($tag1);

        $tag2 = new Tag();
        $tag2->setName('Symfony');
        $this->em->persist($tag2);

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
        $this->em->clear();

        $payload = json_encode([
            'data' => [
                ['type' => 'tags', 'id' => $tag1Id],
            ],
        ]);

        $request = Request::create(
            "/api/articles/{$articleId}/relationships/tags",
            'DELETE',
            [],
            [],
            [],
            ['CONTENT_TYPE' => MediaType::JSON_API],
            $payload
        );

        $response = ($this->controller)($request, 'articles', $articleId, 'tags');
        $this->flush();

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        // Verify in database
        $this->em->clear();
        $updatedArticle = $this->em->find(Article::class, $articleId);
        self::assertNotNull($updatedArticle);
        self::assertCount(1, $updatedArticle->getTags());
    }
}
