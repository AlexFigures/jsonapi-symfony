<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Http\Controller;

use AlexFigures\Symfony\Bridge\Doctrine\Relationship\GenericDoctrineRelationshipHandler;
use AlexFigures\Symfony\Bridge\Doctrine\Repository\GenericDoctrineRepository;
use AlexFigures\Symfony\Filter\Compiler\Doctrine\DoctrineFilterCompiler;
use AlexFigures\Symfony\Filter\Handler\Registry\FilterHandlerRegistry;
use AlexFigures\Symfony\Filter\Handler\Registry\SortHandlerRegistry;
use AlexFigures\Symfony\Filter\Operator\EqualOperator;
use AlexFigures\Symfony\Filter\Operator\Registry;
use AlexFigures\Symfony\Http\Controller\RelatedController;
use AlexFigures\Symfony\Http\Document\DocumentBuilder;
use AlexFigures\Symfony\Http\Negotiation\MediaType;
use AlexFigures\Symfony\Http\Request\QueryParser;
use AlexFigures\Symfony\Resource\Mapper\DefaultReadMapper;
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
 * Integration tests for RelatedController.
 *
 * Tests GET /api/{type}/{id}/{rel} endpoint with real PostgreSQL database.
 */
final class RelatedControllerTest extends DoctrineIntegrationTestCase
{
    use JsonApiResponseAsserts;

    private RelatedController $controller;

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
            $routes->add("jsonapi.{$type}.related.author", new Route("/api/{$type}/{id}/author"));
            $routes->add("jsonapi.{$type}.related.tags", new Route("/api/{$type}/{id}/tags"));
            $routes->add("jsonapi.{$type}.related.articles", new Route("/api/{$type}/{id}/articles"));
            $routes->add("jsonapi.{$type}.relationships.author.show", new Route("/api/{$type}/{id}/relationships/author"));
            $routes->add("jsonapi.{$type}.relationships.tags.show", new Route("/api/{$type}/{id}/relationships/tags"));
            $routes->add("jsonapi.{$type}.relationships.articles.show", new Route("/api/{$type}/{id}/relationships/articles"));
        }

        $context = new RequestContext();
        $context->setScheme('http');
        $context->setHost('localhost');

        $urlGenerator = new UrlGenerator($routes, $context);
        $linkGenerator = new \AlexFigures\Symfony\Http\Link\LinkGenerator($urlGenerator);

        $operatorRegistry = new Registry([new EqualOperator()]);
        $filterHandlerRegistry = new FilterHandlerRegistry();
        $filterCompiler = new DoctrineFilterCompiler($operatorRegistry, $filterHandlerRegistry);
        $sortHandlerRegistry = new SortHandlerRegistry();
        $readMapper = new DefaultReadMapper();

        $repository = new GenericDoctrineRepository(
            $this->managerRegistry,
            $this->registry,
            $filterCompiler,
            $sortHandlerRegistry,
            $readMapper
        );

        $relationshipReader = new GenericDoctrineRelationshipHandler(
            $this->managerRegistry,
            $this->registry,
            $this->accessor
        );

        $documentBuilder = new DocumentBuilder(
            $this->registry,
            $this->accessor,
            $linkGenerator,
            'always'
        );

        $errorBuilder = new \AlexFigures\Symfony\Http\Error\ErrorBuilder(true);
        $errorMapper = new \AlexFigures\Symfony\Http\Error\ErrorMapper($errorBuilder);
        $paginationConfig = new \AlexFigures\Symfony\Http\Request\PaginationConfig(defaultSize: 10, maxSize: 100);
        $sortingWhitelist = new \AlexFigures\Symfony\Http\Request\SortingWhitelist($this->registry);
        $filteringWhitelist = new \AlexFigures\Symfony\Http\Request\FilteringWhitelist($this->registry, $errorMapper);
        $filterParser = new \AlexFigures\Symfony\Filter\Parser\FilterParser();

        $queryParser = new QueryParser(
            $this->registry,
            $paginationConfig,
            $sortingWhitelist,
            $filteringWhitelist,
            $errorMapper,
            $filterParser
        );

        $this->controller = new RelatedController(
            $this->registry,
            $relationshipReader,
            $queryParser,
            $documentBuilder
        );
    }

    /**
     * Test 1: Get related to-one resource.
     */
    public function testGetRelatedToOneResource(): void
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

        $request = Request::create("/api/articles/{$articleId}/author", 'GET');
        $response = ($this->controller)($request, 'articles', $articleId, 'author');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(MediaType::JSON_API, $response->headers->get('Content-Type'));

        $document = $this->decode($response);

        self::assertSame('authors', $document['data']['type']);
        self::assertSame($authorId, $document['data']['id']);
        self::assertSame('John Doe', $document['data']['attributes']['name']);
    }

    /**
     * Test 2: Get related to-one resource (null).
     */
    public function testGetRelatedToOneResourceNull(): void
    {
        $article = new Article();
        $article->setTitle('Test Article');
        $article->setContent('Content');
        $this->em->persist($article);

        $this->em->flush();
        $articleId = $article->getId();
        $this->em->clear();

        $request = Request::create("/api/articles/{$articleId}/author", 'GET');
        $response = ($this->controller)($request, 'articles', $articleId, 'author');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        self::assertNull($document['data']);
    }

    /**
     * Test 3: Get related to-many resources.
     */
    public function testGetRelatedToManyResources(): void
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
        $this->em->clear();

        $request = Request::create("/api/articles/{$articleId}/tags", 'GET');
        $response = ($this->controller)($request, 'articles', $articleId, 'tags');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        self::assertIsArray($document['data']);
        self::assertCount(2, $document['data']);
        self::assertSame('tags', $document['data'][0]['type']);
        self::assertSame('tags', $document['data'][1]['type']);
    }

    /**
     * Test 4: Get related to-many resources (empty).
     */
    public function testGetRelatedToManyResourcesEmpty(): void
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

        $request = Request::create("/api/articles/{$articleId}/tags", 'GET');
        $response = ($this->controller)($request, 'articles', $articleId, 'tags');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        self::assertIsArray($document['data']);
        self::assertCount(0, $document['data']);
    }

    /**
     * Test 5: Error - unknown relationship.
     */
    public function testErrorUnknownRelationship(): void
    {
        $tag = new Tag();
        $tag->setName('PHP');
        $this->em->persist($tag);
        $this->em->flush();

        $tagId = $tag->getId();

        $request = Request::create("/api/tags/{$tagId}/unknown", 'GET');

        $this->expectException(\AlexFigures\Symfony\Http\Exception\NotFoundException::class);
        $this->expectExceptionMessage('Relationship "unknown" not found');

        ($this->controller)($request, 'tags', $tagId, 'unknown');
    }

    /**
     * Test 6: HEAD request returns empty body.
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

        $request = Request::create("/api/articles/{$articleId}/author", 'HEAD');
        $response = ($this->controller)($request, 'articles', $articleId, 'author');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(MediaType::JSON_API, $response->headers->get('Content-Type'));
        self::assertEmpty($response->getContent());
    }
}
