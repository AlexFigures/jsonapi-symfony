<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Http\Controller;

use AlexFigures\Symfony\Bridge\Doctrine\Relationship\GenericDoctrineRelationshipHandler;
use AlexFigures\Symfony\Http\Controller\RelationshipGetController;
use AlexFigures\Symfony\Http\Negotiation\MediaType;
use AlexFigures\Symfony\Http\Relationship\LinkageBuilder;
use AlexFigures\Symfony\Tests\Integration\DoctrineIntegrationTestCase;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Article;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Author;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Tag;
use AlexFigures\Symfony\Tests\Util\JsonApiResponseAsserts;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Integration tests for RelationshipGetController.
 *
 * Tests GET /api/{type}/{id}/relationships/{rel} endpoint with real PostgreSQL database.
 */
final class RelationshipGetControllerTest extends DoctrineIntegrationTestCase
{
    use JsonApiResponseAsserts;

    private RelationshipGetController $controller;

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

        // Set up routing
        $routes = new RouteCollection();
        foreach (['articles', 'authors', 'tags'] as $type) {
            $routes->add("jsonapi.{$type}.index", new Route("/api/{$type}"));
            $routes->add("jsonapi.{$type}.show", new Route("/api/{$type}/{id}"));
            $routes->add("jsonapi.{$type}.relationships.author.show", new Route("/api/{$type}/{id}/relationships/author"));
            $routes->add("jsonapi.{$type}.relationships.tags.show", new Route("/api/{$type}/{id}/relationships/tags"));
        }

        $context = new RequestContext();
        $context->setScheme('http');
        $context->setHost('localhost');

        $urlGenerator = new UrlGenerator($routes, $context);
        $linkGenerator = new \AlexFigures\Symfony\Http\Link\LinkGenerator($urlGenerator);

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

        $this->controller = new RelationshipGetController($linkageBuilder);
    }

    /**
     * Test 1: Get to-one relationship linkage.
     */
    public function testGetToOneRelationshipLinkage(): void
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
        $authorId = $author->getId();
        $this->em->clear();

        $request = Request::create("/api/articles/{$articleId}/relationships/author", 'GET');
        $response = ($this->controller)($request, 'articles', $articleId, 'author');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(MediaType::JSON_API, $response->headers->get('Content-Type'));

        $document = $this->decode($response);

        self::assertIsArray($document['data']);
        self::assertSame('authors', $document['data']['type']);
        self::assertSame($authorId, $document['data']['id']);
    }

    /**
     * Test 2: Get to-one relationship linkage (null).
     */
    public function testGetToOneRelationshipLinkageNull(): void
    {
        $article = new Article();
        $article->setTitle('Test Article');
        $article->setContent('Content');
        $this->em->persist($article);

        $this->em->flush();
        $articleId = $article->getId();
        $this->em->clear();

        $request = Request::create("/api/articles/{$articleId}/relationships/author", 'GET');
        $response = ($this->controller)($request, 'articles', $articleId, 'author');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        self::assertNull($document['data']);
    }

    /**
     * Test 3: Get to-many relationship linkage.
     */
    public function testGetToManyRelationshipLinkage(): void
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
        $tag2Id = $tag2->getId();
        $this->em->clear();

        $request = Request::create("/api/articles/{$articleId}/relationships/tags", 'GET');
        $response = ($this->controller)($request, 'articles', $articleId, 'tags');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        self::assertIsArray($document['data']);
        self::assertCount(2, $document['data']);

        $tagIds = array_column($document['data'], 'id');
        self::assertContains($tag1Id, $tagIds);
        self::assertContains($tag2Id, $tagIds);
    }

    /**
     * Test 4: Get to-many relationship linkage (empty).
     */
    public function testGetToManyRelationshipLinkageEmpty(): void
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

        $request = Request::create("/api/articles/{$articleId}/relationships/tags", 'GET');
        $response = ($this->controller)($request, 'articles', $articleId, 'tags');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        self::assertIsArray($document['data']);
        self::assertCount(0, $document['data']);
    }

    /**
     * Test 5: HEAD request returns empty body.
     */
    public function testHeadRequestReturnsEmptyBody(): void
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

        $request = Request::create("/api/articles/{$articleId}/relationships/author", 'HEAD');
        $response = ($this->controller)($request, 'articles', $articleId, 'author');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(MediaType::JSON_API, $response->headers->get('Content-Type'));
        self::assertEmpty($response->getContent());
    }
}
