<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Integration\Http\Controller;

use JsonApi\Symfony\Bridge\Doctrine\Repository\GenericDoctrineRepository;
use JsonApi\Symfony\Filter\Compiler\Doctrine\DoctrineFilterCompiler;
use JsonApi\Symfony\Filter\Handler\Registry\FilterHandlerRegistry;
use JsonApi\Symfony\Filter\Handler\Registry\SortHandlerRegistry;
use JsonApi\Symfony\Filter\Operator\BetweenOperator;
use JsonApi\Symfony\Filter\Operator\EqualOperator;
use JsonApi\Symfony\Filter\Operator\GreaterOrEqualOperator;
use JsonApi\Symfony\Filter\Operator\GreaterThanOperator;
use JsonApi\Symfony\Filter\Operator\InOperator;
use JsonApi\Symfony\Filter\Operator\IsNullOperator;
use JsonApi\Symfony\Filter\Operator\LessOrEqualOperator;
use JsonApi\Symfony\Filter\Operator\LessThanOperator;
use JsonApi\Symfony\Filter\Operator\LikeOperator;
use JsonApi\Symfony\Filter\Operator\NotEqualOperator;
use JsonApi\Symfony\Filter\Operator\NotInOperator;
use JsonApi\Symfony\Filter\Operator\Registry;
use JsonApi\Symfony\Http\Controller\ResourceController;
use JsonApi\Symfony\Http\Document\DocumentBuilder;
use JsonApi\Symfony\Http\Negotiation\MediaType;
use JsonApi\Symfony\Http\Request\QueryParser;
use JsonApi\Symfony\Tests\Integration\DoctrineIntegrationTestCase;
use JsonApi\Symfony\Tests\Integration\Fixtures\Entity\Article;
use JsonApi\Symfony\Tests\Integration\Fixtures\Entity\Author;
use JsonApi\Symfony\Tests\Integration\Fixtures\Entity\Tag;
use JsonApi\Symfony\Tests\Util\JsonApiResponseAsserts;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Integration tests for ResourceController.
 *
 * Tests GET /api/{type}/{id} endpoint with real PostgreSQL database.
 */
final class ResourceControllerTest extends DoctrineIntegrationTestCase
{
    use JsonApiResponseAsserts;

    private ResourceController $controller;

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
        foreach (['articles', 'authors', 'tags', 'categories'] as $type) {
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
        $linkGenerator = new \JsonApi\Symfony\Http\Link\LinkGenerator($urlGenerator);

        // Set up operator registry
        $operatorRegistry = new Registry([
            new EqualOperator(),
            new NotEqualOperator(),
            new LessThanOperator(),
            new LessOrEqualOperator(),
            new GreaterThanOperator(),
            new GreaterOrEqualOperator(),
            new LikeOperator(),
            new InOperator(),
            new NotInOperator(),
            new BetweenOperator(),
            new IsNullOperator(),
        ]);

        $filterHandlerRegistry = new FilterHandlerRegistry();
        $filterCompiler = new DoctrineFilterCompiler($operatorRegistry, $filterHandlerRegistry);
        $sortHandlerRegistry = new SortHandlerRegistry();

        $repository = new GenericDoctrineRepository(
            $this->em,
            $this->registry,
            $filterCompiler,
            $sortHandlerRegistry
        );

        $documentBuilder = new DocumentBuilder(
            $this->registry,
            $this->accessor,
            $linkGenerator,
            'always'
        );

        // Set up error handling
        $errorBuilder = new \JsonApi\Symfony\Http\Error\ErrorBuilder(true);
        $errorMapper = new \JsonApi\Symfony\Http\Error\ErrorMapper($errorBuilder);

        // Set up pagination configuration
        $paginationConfig = new \JsonApi\Symfony\Http\Request\PaginationConfig(defaultSize: 10, maxSize: 100);

        // Set up sorting whitelist
        $sortingWhitelist = new \JsonApi\Symfony\Http\Request\SortingWhitelist($this->registry);

        // Set up filtering whitelist
        $filteringWhitelist = new \JsonApi\Symfony\Http\Request\FilteringWhitelist($this->registry, $errorMapper);

        // Set up filter parser
        $filterParser = new \JsonApi\Symfony\Filter\Parser\FilterParser();

        $queryParser = new QueryParser(
            $this->registry,
            $paginationConfig,
            $sortingWhitelist,
            $filteringWhitelist,
            $errorMapper,
            $filterParser
        );

        $this->controller = new ResourceController(
            $this->registry,
            $repository,
            $queryParser,
            $documentBuilder
        );
    }

    /**
     * Test 1: Get single resource by ID.
     */
    public function testGetSingleResource(): void
    {
        $tag = new Tag();
        $tag->setName('PHP');
        $this->em->persist($tag);
        $this->em->flush();

        $tagId = $tag->getId();
        $this->em->clear();

        $request = Request::create("/api/tags/{$tagId}", 'GET');
        $response = ($this->controller)($request, 'tags', $tagId);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(MediaType::JSON_API, $response->headers->get('Content-Type'));

        $document = $this->decode($response);

        self::assertSame('tags', $document['data']['type']);
        self::assertSame($tagId, $document['data']['id']);
        self::assertSame('PHP', $document['data']['attributes']['name']);
    }

    /**
     * Test 2: Get resource with relationships.
     */
    public function testGetResourceWithRelationships(): void
    {
        $author = new Author();
        $author->setName('John Doe');
        $author->setEmail('john@example.com');
        $this->em->persist($author);

        $tag = new Tag();
        $tag->setName('PHP');
        $this->em->persist($tag);

        $article = new Article();
        $article->setTitle('Test Article');
        $article->setContent('Content');
        $article->setAuthor($author);
        $article->addTag($tag);
        $this->em->persist($article);

        $this->em->flush();
        $articleId = $article->getId();
        $this->em->clear();

        $request = Request::create("/api/articles/{$articleId}", 'GET');
        $response = ($this->controller)($request, 'articles', $articleId);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        self::assertSame('articles', $document['data']['type']);
        self::assertSame($articleId, $document['data']['id']);
        self::assertArrayHasKey('author', $document['data']['relationships']);
        self::assertArrayHasKey('tags', $document['data']['relationships']);
    }

    /**
     * Test 3: Error - resource not found (404).
     */
    public function testErrorResourceNotFound(): void
    {
        $nonExistentId = '00000000-0000-0000-0000-000000000000';

        $request = Request::create("/api/tags/{$nonExistentId}", 'GET');

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        ($this->controller)($request, 'tags', $nonExistentId);
    }

    /**
     * Test 4: Error - unknown resource type (404).
     */
    public function testErrorUnknownResourceType(): void
    {
        $request = Request::create('/api/unknown-type/some-id', 'GET');

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $this->expectExceptionMessage('Resource type "unknown-type" not found');

        ($this->controller)($request, 'unknown-type', 'some-id');
    }

    /**
     * Test 5: HEAD request returns empty body.
     */
    public function testHeadRequestReturnsEmptyBody(): void
    {
        $tag = new Tag();
        $tag->setName('PHP');
        $this->em->persist($tag);
        $this->em->flush();

        $tagId = $tag->getId();
        $this->em->clear();

        $request = Request::create("/api/tags/{$tagId}", 'HEAD');
        $response = ($this->controller)($request, 'tags', $tagId);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(MediaType::JSON_API, $response->headers->get('Content-Type'));
        self::assertEmpty($response->getContent());
    }

    /**
     * Test 6: Get resource with sparse fieldsets.
     */
    public function testGetResourceWithSparseFieldsets(): void
    {
        $author = new Author();
        $author->setName('John Doe');
        $author->setEmail('john@example.com');
        $this->em->persist($author);
        $this->em->flush();

        $authorId = $author->getId();
        $this->em->clear();

        $request = Request::create("/api/authors/{$authorId}?fields[authors]=name", 'GET');
        $response = ($this->controller)($request, 'authors', $authorId);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        self::assertArrayHasKey('name', $document['data']['attributes']);
        self::assertArrayNotHasKey('email', $document['data']['attributes']);
    }

    /**
     * Test 7: Get resource with include.
     */
    public function testGetResourceWithInclude(): void
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

        $request = Request::create("/api/articles/{$articleId}?include=author", 'GET');
        $response = ($this->controller)($request, 'articles', $articleId);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        self::assertArrayHasKey('included', $document);
        self::assertCount(1, $document['included']);
        self::assertSame('authors', $document['included'][0]['type']);
    }
}

