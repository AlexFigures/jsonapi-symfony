<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Http\Controller;

use AlexFigures\Symfony\Http\Controller\CollectionController;
use AlexFigures\Symfony\Http\Document\DocumentBuilder;
use AlexFigures\Symfony\Http\Error\ErrorBuilder;
use AlexFigures\Symfony\Http\Error\ErrorMapper;
use AlexFigures\Symfony\Http\Link\LinkGenerator;
use AlexFigures\Symfony\Http\Request\FilteringWhitelist;
use AlexFigures\Symfony\Http\Request\PaginationConfig;
use AlexFigures\Symfony\Http\Request\QueryParser;
use AlexFigures\Symfony\Http\Request\SortingWhitelist;
use AlexFigures\Symfony\Tests\Integration\DoctrineIntegrationTestCase;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Article;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Author;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Tag;
use AlexFigures\Symfony\Tests\Util\JsonApiResponseAsserts;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Integration test for cartesian product issue with pagination.
 *
 * When fetching a collection with multiple includes on to-many relationships,
 * Doctrine generates LEFT JOINs that create a cartesian product. This causes
 * the LIMIT to be applied to SQL rows instead of unique entities, resulting
 * in fewer resources returned than requested.
 *
 * Example scenario:
 * - Request: GET /api/authors?include=articles&page[size]=10
 * - Author has 5 articles
 * - SQL returns 5 rows (1 author × 5 articles)
 * - With LIMIT 10, only 2 authors are returned instead of 10
 *
 * This test reproduces the issue and validates the fix.
 */
final class CartesianPaginationTest extends DoctrineIntegrationTestCase
{
    use JsonApiResponseAsserts;

    private CollectionController $controller;

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
        $routes->add('jsonapi.collection', new Route('/api/{type}'));
        $routes->add('jsonapi.resource', new Route('/api/{type}/{id}'));

        foreach (['articles', 'authors', 'tags'] as $type) {
            $routes->add("jsonapi.{$type}.index", new Route("/api/{$type}"));
            $routes->add("jsonapi.{$type}.show", new Route("/api/{$type}/{id}"));
            $routes->add("jsonapi.{$type}.related.author", new Route("/api/{$type}/{id}/author"));
            $routes->add("jsonapi.{$type}.related.articles", new Route("/api/{$type}/{id}/articles"));
            $routes->add("jsonapi.{$type}.related.tags", new Route("/api/{$type}/{id}/tags"));
            $routes->add("jsonapi.{$type}.relationships.author.show", new Route("/api/{$type}/{id}/relationships/author"));
            $routes->add("jsonapi.{$type}.relationships.articles.show", new Route("/api/{$type}/{id}/relationships/articles"));
            $routes->add("jsonapi.{$type}.relationships.tags.show", new Route("/api/{$type}/{id}/relationships/tags"));
        }

        $context = new RequestContext();
        $context->setScheme('http');
        $context->setHost('localhost');

        $urlGenerator = new UrlGenerator($routes, $context);
        $linkGenerator = new LinkGenerator($urlGenerator);

        $documentBuilder = new DocumentBuilder(
            $this->registry,
            $this->accessor,
            $linkGenerator,
            'always'
        );

        $errorMapper = new ErrorMapper(new ErrorBuilder(true));
        $paginationConfig = new PaginationConfig(defaultSize: 10, maxSize: 100);
        $sortingWhitelist = new SortingWhitelist($this->registry);
        $filteringWhitelist = new FilteringWhitelist($this->registry, $errorMapper);

        $queryParser = new QueryParser(
            $this->registry,
            $paginationConfig,
            $sortingWhitelist,
            $filteringWhitelist,
            $errorMapper,
            new \AlexFigures\Symfony\Filter\Parser\FilterParser()
        );

        $this->controller = new CollectionController(
            $this->registry,
            $this->repository,
            $queryParser,
            $documentBuilder
        );
    }

    protected function tearDown(): void
    {
        $this->clearDatabase();
        parent::tearDown();
    }

    /**
     * Test: Cartesian product with OneToMany relationship.
     *
     * Scenario:
     * - Create 10 authors
     * - Each author has 5 articles
     * - Request: GET /api/authors?include=articles&page[size]=10
     * - Expected: 10 authors returned (not 2 due to cartesian product)
     */
    public function testPaginationWithOneToManyInclude(): void
    {
        // Create 10 authors, each with 5 articles
        for ($i = 1; $i <= 10; $i++) {
            $author = new Author();
            $author->setId("author-{$i}");
            $author->setName("Author {$i}");
            $author->setEmail("author{$i}@example.com");
            $this->em->persist($author);

            for ($j = 1; $j <= 5; $j++) {
                $article = new Article();
                $article->setId("article-{$i}-{$j}");
                $article->setTitle("Article {$j} by Author {$i}");
                $article->setContent("Content {$j}");
                $article->setAuthor($author);
                $this->em->persist($article);
            }
        }

        $this->em->flush();
        $this->em->clear();

        // Request first page with 10 items
        $request = Request::create('/api/authors?include=articles&page[size]=10', 'GET');
        $response = ($this->controller)($request, 'authors');

        self::assertSame(200, $response->getStatusCode());

        $document = $this->decode($response);

        // CRITICAL: Should return 10 authors, not fewer due to cartesian product
        self::assertCount(10, $document['data'], 'Expected 10 authors in response, but got fewer due to cartesian product issue');

        // Verify pagination metadata
        self::assertArrayHasKey('meta', $document);
        self::assertSame(10, $document['meta']['size']);
        self::assertSame(1, $document['meta']['page']);
        self::assertSame(10, $document['meta']['total']);

        // Verify included articles are present
        self::assertArrayHasKey('included', $document);
        // Each of 10 authors has 5 articles = 50 articles total
        self::assertCount(50, $document['included']);
    }

    /**
     * Test: Cartesian product with ManyToMany relationship.
     *
     * Scenario:
     * - Create 10 articles
     * - Each article has 3 tags
     * - Request: GET /api/articles?include=tags&page[size]=10
     * - Expected: 10 articles returned (not 3-4 due to cartesian product)
     */
    public function testPaginationWithManyToManyInclude(): void
    {
        // Create tags
        $tags = [];
        for ($i = 1; $i <= 3; $i++) {
            $tag = new Tag();
            $tag->setId("tag-{$i}");
            $tag->setName("Tag {$i}");
            $this->em->persist($tag);
            $tags[] = $tag;
        }

        // Create author
        $author = new Author();
        $author->setId('author-1');
        $author->setName('Test Author');
        $author->setEmail('author@example.com');
        $this->em->persist($author);

        // Create 10 articles, each with all 3 tags
        for ($i = 1; $i <= 10; $i++) {
            $article = new Article();
            $article->setId("article-{$i}");
            $article->setTitle("Article {$i}");
            $article->setContent("Content {$i}");
            $article->setAuthor($author);

            foreach ($tags as $tag) {
                $article->addTag($tag);
            }

            $this->em->persist($article);
        }

        $this->em->flush();
        $this->em->clear();

        // Request first page with 10 items
        $request = Request::create('/api/articles?include=tags&page[size]=10', 'GET');
        $response = ($this->controller)($request, 'articles');

        self::assertSame(200, $response->getStatusCode());

        $document = $this->decode($response);

        // CRITICAL: Should return 10 articles, not fewer due to cartesian product
        self::assertCount(10, $document['data'], 'Expected 10 articles in response, but got fewer due to cartesian product issue');

        // Verify pagination metadata
        self::assertArrayHasKey('meta', $document);
        self::assertSame(10, $document['meta']['size']);
        self::assertSame(1, $document['meta']['page']);
        self::assertSame(10, $document['meta']['total']);

        // Verify included tags are present (deduplicated)
        self::assertArrayHasKey('included', $document);
        self::assertCount(3, $document['included']);
    }

    /**
     * Test: Cartesian product with multiple to-many includes.
     *
     * This is the worst case scenario from the user's description:
     * - Multiple LEFT JOINs on collections
     * - Creates massive cartesian product
     *
     * Scenario:
     * - Create 10 articles
     * - Each article has 1 author (with 5 articles total)
     * - Each article has 3 tags
     * - Request: GET /api/articles?include=author.articles,tags&page[size]=10
     * - Expected: 10 articles returned
     */
    public function testPaginationWithMultipleToManyIncludes(): void
    {
        // Create tags
        $tags = [];
        for ($i = 1; $i <= 3; $i++) {
            $tag = new Tag();
            $tag->setId("tag-{$i}");
            $tag->setName("Tag {$i}");
            $this->em->persist($tag);
            $tags[] = $tag;
        }

        // Create 2 authors, each with 5 articles
        for ($authorIdx = 1; $authorIdx <= 2; $authorIdx++) {
            $author = new Author();
            $author->setId("author-{$authorIdx}");
            $author->setName("Author {$authorIdx}");
            $author->setEmail("author{$authorIdx}@example.com");
            $this->em->persist($author);

            for ($articleIdx = 1; $articleIdx <= 5; $articleIdx++) {
                $article = new Article();
                $article->setId("article-{$authorIdx}-{$articleIdx}");
                $article->setTitle("Article {$articleIdx} by Author {$authorIdx}");
                $article->setContent("Content {$articleIdx}");
                $article->setAuthor($author);

                foreach ($tags as $tag) {
                    $article->addTag($tag);
                }

                $this->em->persist($article);
            }
        }

        $this->em->flush();
        $this->em->clear();

        // Request with multiple includes creating cartesian product
        // Each article joins: author (1) × author.articles (5) × tags (3) = 15 rows per article
        // With 10 articles and LIMIT 10, we'd only get 1 article without fix
        $request = Request::create('/api/articles?include=author.articles,tags&page[size]=10', 'GET');
        $response = ($this->controller)($request, 'articles');

        self::assertSame(200, $response->getStatusCode());

        $document = $this->decode($response);

        // CRITICAL: Should return 10 articles, not 1 due to massive cartesian product
        self::assertCount(10, $document['data'], 'Expected 10 articles in response, but got fewer due to cartesian product with multiple to-many includes');

        // Verify pagination metadata
        self::assertArrayHasKey('meta', $document);
        self::assertSame(10, $document['meta']['size']);
        self::assertSame(1, $document['meta']['page']);
        self::assertSame(10, $document['meta']['total']);
    }
}
