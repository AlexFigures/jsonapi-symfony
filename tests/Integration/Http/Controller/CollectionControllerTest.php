<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Http\Controller;

use AlexFigures\Symfony\Bridge\Doctrine\Repository\GenericDoctrineRepository;
use AlexFigures\Symfony\Filter\Compiler\Doctrine\DoctrineFilterCompiler;
use AlexFigures\Symfony\Filter\Handler\Registry\FilterHandlerRegistry;
use AlexFigures\Symfony\Filter\Handler\Registry\SortHandlerRegistry;
use AlexFigures\Symfony\Filter\Operator\BetweenOperator;
use AlexFigures\Symfony\Filter\Operator\EqualOperator;
use AlexFigures\Symfony\Filter\Operator\GreaterOrEqualOperator;
use AlexFigures\Symfony\Filter\Operator\GreaterThanOperator;
use AlexFigures\Symfony\Filter\Operator\InOperator;
use AlexFigures\Symfony\Filter\Operator\IsNullOperator;
use AlexFigures\Symfony\Filter\Operator\LessOrEqualOperator;
use AlexFigures\Symfony\Filter\Operator\LessThanOperator;
use AlexFigures\Symfony\Filter\Operator\LikeOperator;
use AlexFigures\Symfony\Filter\Operator\NotEqualOperator;
use AlexFigures\Symfony\Filter\Operator\NotInOperator;
use AlexFigures\Symfony\Filter\Operator\Registry;
use AlexFigures\Symfony\Filter\Parser\FilterParser;
use AlexFigures\Symfony\Http\Controller\CollectionController;
use AlexFigures\Symfony\Http\Document\DocumentBuilder;
use AlexFigures\Symfony\Http\Error\ErrorBuilder;
use AlexFigures\Symfony\Http\Error\ErrorMapper;
use AlexFigures\Symfony\Http\Link\LinkGenerator;
use AlexFigures\Symfony\Http\Negotiation\MediaType;
use AlexFigures\Symfony\Http\Request\FilteringWhitelist;
use AlexFigures\Symfony\Http\Request\PaginationConfig;
use AlexFigures\Symfony\Http\Request\QueryParser;
use AlexFigures\Symfony\Http\Request\SortingWhitelist;
use AlexFigures\Symfony\Resource\Mapper\DefaultReadMapper;
use AlexFigures\Symfony\Tests\Integration\DoctrineIntegrationTestCase;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Article;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Author;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Category;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\CategorySynonym;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Tag;
use AlexFigures\Symfony\Tests\Util\JsonApiResponseAsserts;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Uid\Uuid;

/**
 * Integration test for CollectionController with real PostgreSQL database.
 *
 * This test validates JSON:API specification compliance for collection retrieval
 * operations using real Doctrine entities and PostgreSQL connectivity.
 *
 * Test Coverage:
 * - Basic collection retrieval (empty, multiple resources)
 * - Filtering (single/multiple attributes, no matches)
 * - Sorting (ascending, descending, multiple fields)
 * - Pagination (first page, specific page, custom size, last page, out of bounds)
 * - Sparse fieldsets (specific fields, multiple types, invalid fields)
 * - Include relationships (to-one, to-many, nested, multiple, invalid)
 * - Error handling (invalid Accept header, unknown type, invalid parameters)
 * - JSON:API specification compliance
 */
final class CollectionControllerTest extends DoctrineIntegrationTestCase
{
    use JsonApiResponseAsserts;

    private CollectionController $controller;
    private LinkGenerator $linkGenerator;

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

        // Set up routing for LinkGenerator
        $routes = new RouteCollection();
        $routes->add('jsonapi.collection', new Route('/api/{type}'));
        $routes->add('jsonapi.resource', new Route('/api/{type}/{id}'));

        // Add type-specific routes for LinkGenerator
        foreach (['articles', 'authors', 'tags', 'categories', 'category_synonyms', 'comments'] as $type) {
            $routes->add("jsonapi.{$type}.index", new Route("/api/{$type}"));
            $routes->add("jsonapi.{$type}.show", new Route("/api/{$type}/{id}"));

            // Related resource routes
            $routes->add("jsonapi.{$type}.related.author", new Route("/api/{$type}/{id}/author"));
            $routes->add("jsonapi.{$type}.related.tags", new Route("/api/{$type}/{id}/tags"));
            $routes->add("jsonapi.{$type}.related.parent", new Route("/api/{$type}/{id}/parent"));
            $routes->add("jsonapi.{$type}.related.children", new Route("/api/{$type}/{id}/children"));
            $routes->add("jsonapi.{$type}.related.articles", new Route("/api/{$type}/{id}/articles"));
            $routes->add("jsonapi.{$type}.related.category", new Route("/api/{$type}/{id}/category"));

            // Relationship routes
            $routes->add("jsonapi.{$type}.relationships.author.show", new Route("/api/{$type}/{id}/relationships/author"));
            $routes->add("jsonapi.{$type}.relationships.tags.show", new Route("/api/{$type}/{id}/relationships/tags"));
            $routes->add("jsonapi.{$type}.relationships.parent.show", new Route("/api/{$type}/{id}/relationships/parent"));
            $routes->add("jsonapi.{$type}.relationships.children.show", new Route("/api/{$type}/{id}/relationships/children"));
            $routes->add("jsonapi.{$type}.relationships.articles.show", new Route("/api/{$type}/{id}/relationships/articles"));
            $routes->add("jsonapi.{$type}.relationships.category.show", new Route("/api/{$type}/{id}/relationships/category"));
        }

        $context = new RequestContext();
        $context->setScheme('http');
        $context->setHost('localhost');

        $urlGenerator = new UrlGenerator($routes, $context);
        $this->linkGenerator = new LinkGenerator($urlGenerator);

        // Set up DocumentBuilder
        $documentBuilder = new DocumentBuilder(
            $this->registry,
            $this->accessor,
            $this->linkGenerator,
            'always'
        );

        // Set up error handling
        $errorBuilder = new ErrorBuilder(true);
        $errorMapper = new ErrorMapper($errorBuilder);

        // Set up pagination configuration
        $paginationConfig = new PaginationConfig(defaultSize: 10, maxSize: 100);

        // Set up sorting whitelist (allow all sortable fields)
        $sortingWhitelist = new SortingWhitelist($this->registry);

        // Set up filtering whitelist (allow all filterable fields)
        $filteringWhitelist = new FilteringWhitelist($this->registry, $errorMapper);

        // Set up filter parser
        $filterParser = new FilterParser();

        // Set up QueryParser
        $queryParser = new QueryParser(
            $this->registry,
            $paginationConfig,
            $sortingWhitelist,
            $filteringWhitelist,
            $errorMapper,
            $filterParser
        );

        // Set up operator registry with all standard operators
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

        // Set up filter handler registry
        $filterHandlerRegistry = new FilterHandlerRegistry();

        // Set up Doctrine filter compiler
        $filterCompiler = new DoctrineFilterCompiler($operatorRegistry, $filterHandlerRegistry);

        // Set up sort handler registry
        $sortHandlerRegistry = new SortHandlerRegistry();

        // Set up ReadMapper
        $readMapper = new DefaultReadMapper();

        // Set up GenericDoctrineRepository
        $repository = new GenericDoctrineRepository(
            $this->em,
            $this->registry,
            $filterCompiler,
            $sortHandlerRegistry,
            $readMapper
        );

        // Create the controller with all dependencies
        $this->controller = new CollectionController(
            $this->registry,
            $repository,
            $queryParser,
            $documentBuilder
        );
    }

    /**
     * Test 1: Empty collection (no resources).
     *
     * Validates:
     * - 200 OK status
     * - Content-Type header
     * - Empty data array
     * - Pagination meta
     * - Pagination links
     */
    public function testEmptyCollection(): void
    {
        $request = $this->createJsonApiGetRequest('GET', '/api/tags');
        $response = ($this->controller)($request, 'tags');

        // Assert HTTP status and headers
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(MediaType::JSON_API, $response->headers->get('Content-Type'));

        // Decode and validate response structure
        $document = $this->decode($response);

        self::assertArrayHasKey('data', $document);
        self::assertIsArray($document['data']);
        self::assertEmpty($document['data']);

        // Verify pagination meta
        self::assertArrayHasKey('meta', $document);
        self::assertSame(0, $document['meta']['total']);
        self::assertSame(1, $document['meta']['page']);
        self::assertSame(10, $document['meta']['size']);

        // Verify pagination links
        self::assertArrayHasKey('links', $document);
        self::assertArrayHasKey('self', $document['links']);
        self::assertArrayHasKey('first', $document['links']);
    }

    /**
     * Test 2: Collection with multiple resources.
     *
     * Validates:
     * - 200 OK status
     * - Data array with multiple items
     * - Each item has type, id, attributes
     * - Pagination meta reflects correct total
     */
    public function testCollectionWithMultipleResources(): void
    {
        // Create test data
        $tag1 = new Tag();
        $tag1->setName('PHP');
        $this->em->persist($tag1);

        $tag2 = new Tag();
        $tag2->setName('Symfony');
        $this->em->persist($tag2);

        $tag3 = new Tag();
        $tag3->setName('Doctrine');
        $this->em->persist($tag3);

        $this->em->flush();
        $this->em->clear();

        $request = $this->createJsonApiGetRequest('GET', '/api/tags');
        $response = ($this->controller)($request, 'tags');

        // Assert HTTP status
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        // Decode and validate response
        $document = $this->decode($response);

        self::assertArrayHasKey('data', $document);
        self::assertIsArray($document['data']);
        self::assertCount(3, $document['data']);

        // Verify each item structure
        foreach ($document['data'] as $item) {
            self::assertArrayHasKey('type', $item);
            self::assertSame('tags', $item['type']);
            self::assertArrayHasKey('id', $item);
            self::assertArrayHasKey('attributes', $item);
            self::assertArrayHasKey('name', $item['attributes']);
        }

        // Verify pagination meta
        self::assertSame(3, $document['meta']['total']);
        self::assertSame(1, $document['meta']['page']);
    }

    /**
     * Test 3: Filter by single attribute.
     *
     * Validates:
     * - Filtering works correctly
     * - Only matching resources returned
     * - Non-matching resources excluded
     */
    public function testFilterBySingleAttribute(): void
    {
        // Create test data
        $tag1 = new Tag();
        $tag1->setName('PHP');
        $this->em->persist($tag1);

        $tag2 = new Tag();
        $tag2->setName('Symfony');
        $this->em->persist($tag2);

        $tag3 = new Tag();
        $tag3->setName('PHP Framework');
        $this->em->persist($tag3);

        $this->em->flush();
        $this->em->clear();

        $request = $this->createJsonApiGetRequest('GET', '/api/tags?filter[name]=PHP');
        $response = ($this->controller)($request, 'tags');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        self::assertCount(1, $document['data']);
        self::assertSame('PHP', $document['data'][0]['attributes']['name']);
        self::assertSame(1, $document['meta']['total']);
    }

    /**
     * Test 4: Filter with no matches (empty result).
     *
     * Validates:
     * - Empty data array when no matches
     * - Total count is 0
     */
    public function testFilterWithNoMatches(): void
    {
        // Create test data
        $tag1 = new Tag();
        $tag1->setName('PHP');
        $this->em->persist($tag1);

        $this->em->flush();
        $this->em->clear();

        $request = $this->createJsonApiGetRequest('GET', '/api/tags?filter[name]=NonExistent');
        $response = ($this->controller)($request, 'tags');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        self::assertEmpty($document['data']);
        self::assertSame(0, $document['meta']['total']);
    }

    /**
     * Test 5: Sort ascending by single field.
     *
     * Validates:
     * - Resources sorted in ascending order
     * - Sort parameter is respected
     */
    public function testSortAscendingBySingleField(): void
    {
        // Create test data in random order
        $tag1 = new Tag();
        $tag1->setName('Zend');
        $this->em->persist($tag1);

        $tag2 = new Tag();
        $tag2->setName('Angular');
        $this->em->persist($tag2);

        $tag3 = new Tag();
        $tag3->setName('Symfony');
        $this->em->persist($tag3);

        $this->em->flush();
        $this->em->clear();

        $request = $this->createJsonApiGetRequest('GET', '/api/tags?sort=name');
        $response = ($this->controller)($request, 'tags');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        self::assertCount(3, $document['data']);
        self::assertSame('Angular', $document['data'][0]['attributes']['name']);
        self::assertSame('Symfony', $document['data'][1]['attributes']['name']);
        self::assertSame('Zend', $document['data'][2]['attributes']['name']);
    }

    /**
     * Test 6: Sort descending by single field.
     *
     * Validates:
     * - Resources sorted in descending order
     * - Minus prefix for descending sort
     */
    public function testSortDescendingBySingleField(): void
    {
        // Create test data
        $tag1 = new Tag();
        $tag1->setName('Angular');
        $this->em->persist($tag1);

        $tag2 = new Tag();
        $tag2->setName('Symfony');
        $this->em->persist($tag2);

        $tag3 = new Tag();
        $tag3->setName('Zend');
        $this->em->persist($tag3);

        $this->em->flush();
        $this->em->clear();

        $request = $this->createJsonApiGetRequest('GET', '/api/tags?sort=-name');
        $response = ($this->controller)($request, 'tags');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        self::assertCount(3, $document['data']);
        self::assertSame('Zend', $document['data'][0]['attributes']['name']);
        self::assertSame('Symfony', $document['data'][1]['attributes']['name']);
        self::assertSame('Angular', $document['data'][2]['attributes']['name']);
    }

    /**
     * Test 7: Pagination - first page with default page size.
     *
     * Validates:
     * - Default page size is 10
     * - First page returns correct items
     * - Pagination links include next
     */
    public function testPaginationFirstPageDefaultSize(): void
    {
        // Create 15 tags
        for ($i = 1; $i <= 15; $i++) {
            $tag = new Tag();
            $tag->setName("Tag {$i}");
            $this->em->persist($tag);
        }

        $this->em->flush();
        $this->em->clear();

        $request = $this->createJsonApiGetRequest('GET', '/api/tags');
        $response = ($this->controller)($request, 'tags');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        self::assertCount(10, $document['data']); // Default page size
        self::assertSame(15, $document['meta']['total']);
        self::assertSame(1, $document['meta']['page']);
        self::assertSame(10, $document['meta']['size']);

        // Verify pagination links
        self::assertArrayHasKey('next', $document['links']);
        self::assertArrayHasKey('last', $document['links']);
    }

    /**
     * Test 8: Pagination - specific page number.
     *
     * Validates:
     * - Page number parameter works
     * - Correct items returned for page 2
     * - Pagination links include prev and next
     */
    public function testPaginationSpecificPageNumber(): void
    {
        // Create 25 tags
        for ($i = 1; $i <= 25; $i++) {
            $tag = new Tag();
            $tag->setName("Tag {$i}");
            $this->em->persist($tag);
        }

        $this->em->flush();
        $this->em->clear();

        $request = $this->createJsonApiGetRequest('GET', '/api/tags?page[number]=2');
        $response = ($this->controller)($request, 'tags');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        self::assertCount(10, $document['data']);
        self::assertSame(25, $document['meta']['total']);
        self::assertSame(2, $document['meta']['page']);

        // Verify pagination links
        self::assertArrayHasKey('prev', $document['links']);
        self::assertArrayHasKey('next', $document['links']);
        self::assertArrayHasKey('first', $document['links']);
        self::assertArrayHasKey('last', $document['links']);
    }

    /**
     * Test 9: Pagination - custom page size.
     *
     * Validates:
     * - Custom page size parameter works
     * - Correct number of items returned
     */
    public function testPaginationCustomPageSize(): void
    {
        // Create 20 tags
        for ($i = 1; $i <= 20; $i++) {
            $tag = new Tag();
            $tag->setName("Tag {$i}");
            $this->em->persist($tag);
        }

        $this->em->flush();
        $this->em->clear();

        $request = $this->createJsonApiGetRequest('GET', '/api/tags?page[size]=5');
        $response = ($this->controller)($request, 'tags');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        self::assertCount(5, $document['data']);
        self::assertSame(20, $document['meta']['total']);
        self::assertSame(5, $document['meta']['size']);
    }

    /**
     * Test 10: Pagination - last page (partial results).
     *
     * Validates:
     * - Last page returns remaining items
     * - No next link on last page
     */
    public function testPaginationLastPagePartialResults(): void
    {
        // Create 23 tags (last page will have 3 items with page size 10)
        for ($i = 1; $i <= 23; $i++) {
            $tag = new Tag();
            $tag->setName("Tag {$i}");
            $this->em->persist($tag);
        }

        $this->em->flush();
        $this->em->clear();

        $request = $this->createJsonApiGetRequest('GET', '/api/tags?page[number]=3');
        $response = ($this->controller)($request, 'tags');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        self::assertCount(3, $document['data']); // Only 3 items on last page
        self::assertSame(23, $document['meta']['total']);
        self::assertSame(3, $document['meta']['page']);

        // Verify no next link on last page
        self::assertArrayNotHasKey('next', $document['links']);
        self::assertArrayHasKey('prev', $document['links']);
    }

    /**
     * Test 11: Sparse fieldsets - request specific fields only.
     *
     * Validates:
     * - Only requested fields are returned
     * - Other fields are excluded
     */
    public function testSparseFieldsetsSpecificFields(): void
    {
        // Create test data
        $author = new Author();
        $author->setName('John Doe');
        $author->setEmail('john@example.com');
        $this->em->persist($author);

        $this->em->flush();
        $this->em->clear();

        $request = $this->createJsonApiGetRequest('GET', '/api/authors?fields[authors]=name');
        $response = ($this->controller)($request, 'authors');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        self::assertCount(1, $document['data']);
        self::assertArrayHasKey('name', $document['data'][0]['attributes']);
        self::assertArrayNotHasKey('email', $document['data'][0]['attributes']);
    }

    /**
     * Test 12: Include to-one relationship.
     *
     * Validates:
     * - Included resources are present
     * - Relationship data references included resources
     */
    public function testIncludeToOneRelationship(): void
    {
        // Create test data
        $author = new Author();
        $author->setName('Jane Smith');
        $author->setEmail('jane@example.com');
        $this->em->persist($author);

        $article = new Article();
        $article->setTitle('Test Article');
        $article->setContent('Content here');
        $article->setAuthor($author);
        $this->em->persist($article);

        $this->em->flush();
        $authorId = $author->getId();
        $this->em->clear();

        $request = $this->createJsonApiGetRequest('GET', '/api/articles?include=author');
        $response = ($this->controller)($request, 'articles');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        // Verify included section exists
        self::assertArrayHasKey('included', $document);
        self::assertCount(1, $document['included']);

        // Verify included author
        $includedAuthor = $document['included'][0];
        self::assertSame('authors', $includedAuthor['type']);
        self::assertSame($authorId, $includedAuthor['id']);
        self::assertSame('Jane Smith', $includedAuthor['attributes']['name']);

        // Verify relationship data
        self::assertArrayHasKey('relationships', $document['data'][0]);
        self::assertArrayHasKey('author', $document['data'][0]['relationships']);
        self::assertSame($authorId, $document['data'][0]['relationships']['author']['data']['id']);
    }

    /**
     * Test 13: Include to-many relationship.
     *
     * Validates:
     * - Multiple included resources
     * - Relationship data is array
     */
    public function testIncludeToManyRelationship(): void
    {
        // Create test data
        $tag1 = new Tag();
        $tag1->setName('PHP');
        $this->em->persist($tag1);

        $tag2 = new Tag();
        $tag2->setName('Symfony');
        $this->em->persist($tag2);

        $author = new Author();
        $author->setName('John Doe');
        $author->setEmail('john@example.com');
        $this->em->persist($author);

        $article = new Article();
        $article->setTitle('Test Article');
        $article->setContent('Content');
        $article->setAuthor($author);
        $article->addTag($tag1);
        $article->addTag($tag2);
        $this->em->persist($article);

        $this->em->flush();
        $tag1Id = $tag1->getId();
        $tag2Id = $tag2->getId();
        $this->em->clear();

        $request = $this->createJsonApiGetRequest('GET', '/api/articles?include=tags');
        $response = ($this->controller)($request, 'articles');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        // Verify included section
        self::assertArrayHasKey('included', $document);
        self::assertCount(2, $document['included']);

        // Verify included tags
        $includedIds = array_column($document['included'], 'id');
        self::assertContains($tag1Id, $includedIds);
        self::assertContains($tag2Id, $includedIds);

        // Verify relationship data is array
        self::assertIsArray($document['data'][0]['relationships']['tags']['data']);
        self::assertCount(2, $document['data'][0]['relationships']['tags']['data']);
    }

    /**
     * Test 14: Error - unknown resource type (404 Not Found).
     *
     * Validates:
     * - 404 status for unknown type
     * - Error response format
     */
    public function testErrorUnknownResourceType(): void
    {
        $request = $this->createJsonApiGetRequest('GET', '/api/unknown-type');

        $this->expectException(\AlexFigures\Symfony\Http\Exception\NotFoundException::class);
        $this->expectExceptionMessage('Resource type "unknown-type" not found');

        ($this->controller)($request, 'unknown-type');
    }

    /**
     * Test 15: Error - invalid query parameter (400 Bad Request).
     *
     * Validates:
     * - 400 status for invalid parameters
     * - Error details in response
     */
    public function testErrorInvalidQueryParameter(): void
    {
        $request = $this->createJsonApiGetRequest('GET', '/api/tags?page[number]=0');

        $this->expectException(\AlexFigures\Symfony\Http\Exception\BadRequestException::class);

        ($this->controller)($request, 'tags');
    }

    /**
     * Test 16: Pagination - out of bounds page number.
     *
     * Validates:
     * - Empty data array for out of bounds page
     * - Still returns 200 OK
     */
    public function testPaginationOutOfBoundsPageNumber(): void
    {
        // Create 5 tags
        for ($i = 1; $i <= 5; $i++) {
            $tag = new Tag();
            $tag->setName("Tag {$i}");
            $this->em->persist($tag);
        }

        $this->em->flush();
        $this->em->clear();

        // Request page 10 (out of bounds)
        $request = $this->createJsonApiGetRequest('GET', '/api/tags?page[number]=10');
        $response = ($this->controller)($request, 'tags');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        self::assertEmpty($document['data']);
        self::assertSame(5, $document['meta']['total']);
        self::assertSame(10, $document['meta']['page']);
    }

    /**
     * Test 17: Sort by multiple fields.
     *
     * Validates:
     * - Multiple sort fields work correctly
     * - Sort order is applied in sequence
     */
    public function testSortByMultipleFields(): void
    {
        // Create authors with same name but different emails
        $author1 = new Author();
        $author1->setName('John Doe');
        $author1->setEmail('john.z@example.com');
        $this->em->persist($author1);

        $author2 = new Author();
        $author2->setName('John Doe');
        $author2->setEmail('john.a@example.com');
        $this->em->persist($author2);

        $author3 = new Author();
        $author3->setName('Alice Smith');
        $author3->setEmail('alice@example.com');
        $this->em->persist($author3);

        $this->em->flush();
        $this->em->clear();

        // Sort by name ascending, then email ascending
        $request = $this->createJsonApiGetRequest('GET', '/api/authors?sort=name,email');
        $response = ($this->controller)($request, 'authors');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        self::assertCount(3, $document['data']);
        self::assertSame('Alice Smith', $document['data'][0]['attributes']['name']);
        self::assertSame('John Doe', $document['data'][1]['attributes']['name']);
        self::assertSame('john.a@example.com', $document['data'][1]['attributes']['email']);
        self::assertSame('John Doe', $document['data'][2]['attributes']['name']);
        self::assertSame('john.z@example.com', $document['data'][2]['attributes']['email']);
    }

    /**
     * Test 18: Filter by multiple attributes.
     *
     * Validates:
     * - Multiple filters work together (AND logic)
     * - Only resources matching all filters returned
     */
    public function testFilterByMultipleAttributes(): void
    {
        // Create test data
        $author1 = new Author();
        $author1->setName('John Doe');
        $author1->setEmail('john@example.com');
        $this->em->persist($author1);

        $author2 = new Author();
        $author2->setName('John Smith');
        $author2->setEmail('john.smith@example.com');
        $this->em->persist($author2);

        $author3 = new Author();
        $author3->setName('Jane Doe');
        $author3->setEmail('jane@example.com');
        $this->em->persist($author3);

        $this->em->flush();
        $this->em->clear();

        // Filter by name AND email pattern
        $request = $this->createJsonApiGetRequest('GET', '/api/authors?filter[name]=John Doe&filter[email]=john@example.com');
        $response = ($this->controller)($request, 'authors');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        self::assertCount(1, $document['data']);
        self::assertSame('John Doe', $document['data'][0]['attributes']['name']);
        self::assertSame('john@example.com', $document['data'][0]['attributes']['email']);
    }

    /**
     * Test 19: Include multiple relationships.
     *
     * Validates:
     * - Multiple includes work together
     * - All included resources present
     */
    public function testIncludeMultipleRelationships(): void
    {
        // Create test data
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
        $this->em->clear();

        $request = $this->createJsonApiGetRequest('GET', '/api/articles?include=author,tags');
        $response = ($this->controller)($request, 'articles');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        // Verify included section has all resources (1 author + 2 tags)
        self::assertArrayHasKey('included', $document);
        self::assertCount(3, $document['included']);

        // Verify types
        $types = array_column($document['included'], 'type');
        self::assertContains('authors', $types);
        self::assertContains('tags', $types);
    }

    /**
     * Test 20: Combined filtering, sorting, and pagination.
     *
     * Validates:
     * - All query parameters work together
     * - Correct results with complex query
     */
    public function testCombinedFilteringSortingPagination(): void
    {
        // Create 20 tags with different names
        for ($i = 1; $i <= 20; $i++) {
            $tag = new Tag();
            $tag->setName($i % 2 === 0 ? "Even Tag {$i}" : "Odd Tag {$i}");
            $this->em->persist($tag);
        }

        $this->em->flush();
        $this->em->clear();

        // Filter for "Even", sort by name descending, page 1 with size 5
        $request = $this->createJsonApiGetRequest('GET', '/api/tags?filter[name][like]=%Even%&sort=-name&page[size]=5&page[number]=1');
        $response = ($this->controller)($request, 'tags');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        // Should have 5 items (page size)
        self::assertCount(5, $document['data']);

        // Total should be 10 (half of 20)
        self::assertSame(10, $document['meta']['total']);

        // Verify all items contain "Even"
        foreach ($document['data'] as $item) {
            self::assertStringContainsString('Even', $item['attributes']['name']);
        }

        // Verify descending order (alphabetically: "Even Tag 8", "Even Tag 6", "Even Tag 4", "Even Tag 20", "Even Tag 2")
        self::assertStringContainsString('Even Tag', $document['data'][0]['attributes']['name']);

        // Verify first item is greater than last item (descending order)
        self::assertGreaterThan(
            $document['data'][4]['attributes']['name'],
            $document['data'][0]['attributes']['name']
        );
    }

    /**
     * Test 21: Sort by to-one relationship field (ascending).
     *
     * NOTE: This test documents expected behavior for sorting by relationship fields.
     * Currently not implemented - this is a TDD test that may fail until feature is added.
     */
    public function testSortByToOneRelationshipFieldAscending(): void
    {
        // Create authors with different names
        $authorA = new Author();
        $authorA->setName('Alice');
        $authorA->setEmail('alice@example.com');
        $this->em->persist($authorA);

        $authorB = new Author();
        $authorB->setName('Bob');
        $authorB->setEmail('bob@example.com');
        $this->em->persist($authorB);

        $authorC = new Author();
        $authorC->setName('Charlie');
        $authorC->setEmail('charlie@example.com');
        $this->em->persist($authorC);

        // Create articles with different authors
        $article1 = new Article();
        $article1->setTitle('Article by Charlie');
        $article1->setContent('Content 1');
        $article1->setAuthor($authorC);
        $this->em->persist($article1);

        $article2 = new Article();
        $article2->setTitle('Article by Alice');
        $article2->setContent('Content 2');
        $article2->setAuthor($authorA);
        $this->em->persist($article2);

        $article3 = new Article();
        $article3->setTitle('Article by Bob');
        $article3->setContent('Content 3');
        $article3->setAuthor($authorB);
        $this->em->persist($article3);

        $this->em->flush();
        $this->em->clear();

        // Request collection sorted by author.name (ascending)
        $request = $this->createJsonApiGetRequest('GET', '/api/articles?sort=author.name');
        $response = ($this->controller)($request, 'articles');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        self::assertIsArray($document['data']);
        self::assertCount(3, $document['data']);

        // Verify sorting: Alice, Bob, Charlie
        self::assertSame('Article by Alice', $document['data'][0]['attributes']['title']);
        self::assertSame('Article by Bob', $document['data'][1]['attributes']['title']);
        self::assertSame('Article by Charlie', $document['data'][2]['attributes']['title']);
    }

    /**
     * Test 22: Sort by to-one relationship field (descending).
     *
     * NOTE: This test documents expected behavior for sorting by relationship fields.
     * Currently not implemented - this is a TDD test that may fail until feature is added.
     */
    public function testSortByToOneRelationshipFieldDescending(): void
    {
        // Create authors with different names
        $authorA = new Author();
        $authorA->setName('Alice');
        $authorA->setEmail('alice@example.com');
        $this->em->persist($authorA);

        $authorB = new Author();
        $authorB->setName('Bob');
        $authorB->setEmail('bob@example.com');
        $this->em->persist($authorB);

        $authorC = new Author();
        $authorC->setName('Charlie');
        $authorC->setEmail('charlie@example.com');
        $this->em->persist($authorC);

        // Create articles with different authors
        $article1 = new Article();
        $article1->setTitle('Article by Alice');
        $article1->setContent('Content 1');
        $article1->setAuthor($authorA);
        $this->em->persist($article1);

        $article2 = new Article();
        $article2->setTitle('Article by Bob');
        $article2->setContent('Content 2');
        $article2->setAuthor($authorB);
        $this->em->persist($article2);

        $article3 = new Article();
        $article3->setTitle('Article by Charlie');
        $article3->setContent('Content 3');
        $article3->setAuthor($authorC);
        $this->em->persist($article3);

        $this->em->flush();
        $this->em->clear();

        // Request collection sorted by author.name (descending)
        $request = $this->createJsonApiGetRequest('GET', '/api/articles?sort=-author.name');
        $response = ($this->controller)($request, 'articles');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        self::assertIsArray($document['data']);
        self::assertCount(3, $document['data']);

        // Verify sorting: Charlie, Bob, Alice
        self::assertSame('Article by Charlie', $document['data'][0]['attributes']['title']);
        self::assertSame('Article by Bob', $document['data'][1]['attributes']['title']);
        self::assertSame('Article by Alice', $document['data'][2]['attributes']['title']);
    }

    /**
     * Test 23: Sort by multiple fields including relationship field.
     *
     * NOTE: This test documents expected behavior for sorting by relationship fields.
     * Currently not implemented - this is a TDD test that may fail until feature is added.
     */
    public function testSortByMultipleFieldsIncludingRelationship(): void
    {
        // Create authors
        $authorA = new Author();
        $authorA->setName('Alice');
        $authorA->setEmail('alice@example.com');
        $this->em->persist($authorA);

        $authorB = new Author();
        $authorB->setName('Bob');
        $authorB->setEmail('bob@example.com');
        $this->em->persist($authorB);

        // Create articles - same author, different titles
        $article1 = new Article();
        $article1->setTitle('Zebra Article');
        $article1->setContent('Content 1');
        $article1->setAuthor($authorA);
        $this->em->persist($article1);

        $article2 = new Article();
        $article2->setTitle('Apple Article');
        $article2->setContent('Content 2');
        $article2->setAuthor($authorA);
        $this->em->persist($article2);

        $article3 = new Article();
        $article3->setTitle('Banana Article');
        $article3->setContent('Content 3');
        $article3->setAuthor($authorB);
        $this->em->persist($article3);

        $this->em->flush();
        $this->em->clear();

        // Request collection sorted by author.name, then title
        $request = $this->createJsonApiGetRequest('GET', '/api/articles?sort=author.name,title');
        $response = ($this->controller)($request, 'articles');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        self::assertIsArray($document['data']);
        self::assertCount(3, $document['data']);

        // Verify sorting: Alice's articles (Apple, Zebra), then Bob's article (Banana)
        self::assertSame('Apple Article', $document['data'][0]['attributes']['title']);
        self::assertSame('Zebra Article', $document['data'][1]['attributes']['title']);
        self::assertSame('Banana Article', $document['data'][2]['attributes']['title']);
    }

    /**
     * Test 24: Nested includes (include author and their other articles).
     */
    public function testNestedIncludes(): void
    {
        // Create author
        $author = new Author();
        $author->setName('John Doe');
        $author->setEmail('john@example.com');
        $this->em->persist($author);

        // Create multiple articles by same author
        $article1 = new Article();
        $article1->setTitle('First Article');
        $article1->setContent('Content 1');
        $article1->setAuthor($author);
        $this->em->persist($article1);

        $article2 = new Article();
        $article2->setTitle('Second Article');
        $article2->setContent('Content 2');
        $article2->setAuthor($author);
        $this->em->persist($article2);

        $article3 = new Article();
        $article3->setTitle('Third Article');
        $article3->setContent('Content 3');
        $article3->setAuthor($author);
        $this->em->persist($article3);

        $this->em->flush();
        $this->em->clear();

        // Request collection with nested include: author.articles
        $request = $this->createJsonApiGetRequest('GET', '/api/articles?include=author.articles');
        $response = ($this->controller)($request, 'articles');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        self::assertArrayHasKey('included', $document);

        // Should include author and all their articles
        $includedTypes = array_column($document['included'], 'type');
        self::assertContains('authors', $includedTypes);
        self::assertContains('articles', $includedTypes);

        // Count articles in included (should be at least 2 - the other articles by same author)
        $includedArticles = array_filter($document['included'], fn ($item) => $item['type'] === 'articles');
        self::assertGreaterThanOrEqual(2, count($includedArticles));
    }

    /**
     * Test 25: Multiple unrelated includes.
     */
    public function testMultipleUnrelatedIncludes(): void
    {
        // Create author
        $author = new Author();
        $author->setName('John Doe');
        $author->setEmail('john@example.com');
        $this->em->persist($author);

        // Create tags
        $tag1 = new Tag();
        $tag1->setName('PHP');
        $this->em->persist($tag1);

        $tag2 = new Tag();
        $tag2->setName('Symfony');
        $this->em->persist($tag2);

        $tag3 = new Tag();
        $tag3->setName('Doctrine');
        $this->em->persist($tag3);

        // Create multiple articles with different relationships
        $article1 = new Article();
        $article1->setTitle('First Article');
        $article1->setContent('Content 1');
        $article1->setAuthor($author);
        $article1->addTag($tag1);
        $article1->addTag($tag2);
        $this->em->persist($article1);

        $article2 = new Article();
        $article2->setTitle('Second Article');
        $article2->setContent('Content 2');
        $article2->setAuthor($author);
        $article2->addTag($tag3);
        $this->em->persist($article2);

        $this->em->flush();
        $this->em->clear();

        // Request collection with multiple unrelated includes
        $request = $this->createJsonApiGetRequest('GET', '/api/articles?include=author,tags');
        $response = ($this->controller)($request, 'articles');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        self::assertArrayHasKey('included', $document);

        // Verify both types are included
        $includedTypes = array_column($document['included'], 'type');
        self::assertContains('authors', $includedTypes);
        self::assertContains('tags', $includedTypes);

        // Verify counts
        $authors = array_filter($document['included'], fn ($item) => $item['type'] === 'authors');
        $tags = array_filter($document['included'], fn ($item) => $item['type'] === 'tags');

        self::assertCount(1, $authors);
        self::assertCount(3, $tags); // All 3 unique tags should be included
    }

    /**
     * Test 26: Include with empty relationships (null and empty arrays).
     */
    public function testIncludeWithEmptyRelationships(): void
    {
        // Create article with no author and no tags
        $article1 = new Article();
        $article1->setTitle('Article without relationships');
        $article1->setContent('Content 1');
        $this->em->persist($article1);

        // Create article with author but no tags
        $author = new Author();
        $author->setName('John Doe');
        $author->setEmail('john@example.com');
        $this->em->persist($author);

        $article2 = new Article();
        $article2->setTitle('Article with author only');
        $article2->setContent('Content 2');
        $article2->setAuthor($author);
        $this->em->persist($article2);

        $this->em->flush();
        $this->em->clear();

        // Request collection with includes for potentially empty relationships
        $request = $this->createJsonApiGetRequest('GET', '/api/articles?include=author,tags');
        $response = ($this->controller)($request, 'articles');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        self::assertIsArray($document['data']);
        self::assertCount(2, $document['data']);

        // First article should have null author and empty tags array
        self::assertArrayHasKey('relationships', $document['data'][0]);
        self::assertNull($document['data'][0]['relationships']['author']['data']);
        self::assertIsArray($document['data'][0]['relationships']['tags']['data']);
        self::assertEmpty($document['data'][0]['relationships']['tags']['data']);

        // Second article should have author but empty tags
        self::assertIsArray($document['data'][1]['relationships']['author']['data']);
        self::assertIsArray($document['data'][1]['relationships']['tags']['data']);
        self::assertEmpty($document['data'][1]['relationships']['tags']['data']);

        // Included should only contain the one author
        if (isset($document['included'])) {
            $includedTypes = array_column($document['included'], 'type');
            self::assertContains('authors', $includedTypes);
            self::assertNotContains('tags', $includedTypes); // No tags to include
        }
    }

    /**
     * Test 27: Include with sparse fieldsets on primary resource.
     */
    public function testIncludeWithSparseFieldsetsOnPrimaryResource(): void
    {
        // Create author
        $author = new Author();
        $author->setName('John Doe');
        $author->setEmail('john@example.com');
        $this->em->persist($author);

        // Create article
        $article = new Article();
        $article->setTitle('Test Article');
        $article->setContent('This is the content');
        $article->setAuthor($author);
        $this->em->persist($article);

        $this->em->flush();
        $this->em->clear();

        // Request with include and sparse fieldsets on primary resource
        $request = $this->createJsonApiGetRequest('GET', '/api/articles?include=author&fields[articles]=title');
        $response = ($this->controller)($request, 'articles');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        // Primary resource should only have 'title' attribute
        self::assertArrayHasKey('title', $document['data'][0]['attributes']);
        self::assertArrayNotHasKey('content', $document['data'][0]['attributes']);

        // Included author should have all fields (no sparse fieldsets applied)
        self::assertArrayHasKey('included', $document);
        $author = array_values(array_filter($document['included'], fn ($item) => $item['type'] === 'authors'))[0];
        self::assertArrayHasKey('name', $author['attributes']);
        self::assertArrayHasKey('email', $author['attributes']);
    }

    /**
     * Test 28: Include with sparse fieldsets on included resource.
     */
    public function testIncludeWithSparseFieldsetsOnIncludedResource(): void
    {
        // Create author
        $author = new Author();
        $author->setName('John Doe');
        $author->setEmail('john@example.com');
        $this->em->persist($author);

        // Create article
        $article = new Article();
        $article->setTitle('Test Article');
        $article->setContent('This is the content');
        $article->setAuthor($author);
        $this->em->persist($article);

        $this->em->flush();
        $this->em->clear();

        // Request with include and sparse fieldsets on included resource
        $request = $this->createJsonApiGetRequest('GET', '/api/articles?include=author&fields[authors]=name');
        $response = ($this->controller)($request, 'articles');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        // Primary resource should have all fields
        self::assertArrayHasKey('title', $document['data'][0]['attributes']);
        self::assertArrayHasKey('content', $document['data'][0]['attributes']);

        // Included author should only have 'name' attribute
        self::assertArrayHasKey('included', $document);
        $author = array_values(array_filter($document['included'], fn ($item) => $item['type'] === 'authors'))[0];
        self::assertArrayHasKey('name', $author['attributes']);
        self::assertArrayNotHasKey('email', $author['attributes']);
    }

    /**
     * Test 29: Include with sparse fieldsets on both primary and included resources.
     */
    public function testIncludeWithSparseFieldsetsOnBothResources(): void
    {
        // Create author
        $author = new Author();
        $author->setName('John Doe');
        $author->setEmail('john@example.com');
        $this->em->persist($author);

        // Create tags
        $tag1 = new Tag();
        $tag1->setName('PHP');
        $this->em->persist($tag1);

        $tag2 = new Tag();
        $tag2->setName('Symfony');
        $this->em->persist($tag2);

        // Create article
        $article = new Article();
        $article->setTitle('Test Article');
        $article->setContent('This is the content');
        $article->setAuthor($author);
        $article->addTag($tag1);
        $article->addTag($tag2);
        $this->em->persist($article);

        $this->em->flush();
        $this->em->clear();

        // Request with include and sparse fieldsets on multiple resources
        $request = $this->createJsonApiGetRequest(
            'GET',
            '/api/articles?include=author,tags&fields[articles]=title&fields[authors]=name&fields[tags]=name'
        );
        $response = ($this->controller)($request, 'articles');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        // Primary resource should only have 'title' attribute
        self::assertArrayHasKey('title', $document['data'][0]['attributes']);
        self::assertArrayNotHasKey('content', $document['data'][0]['attributes']);

        // Included author should only have 'name' attribute
        self::assertArrayHasKey('included', $document);
        $authors = array_filter($document['included'], fn ($item) => $item['type'] === 'authors');
        $author = array_values($authors)[0];
        self::assertArrayHasKey('name', $author['attributes']);
        self::assertArrayNotHasKey('email', $author['attributes']);

        // Included tags should only have 'name' attribute
        $tags = array_filter($document['included'], fn ($item) => $item['type'] === 'tags');
        foreach ($tags as $tag) {
            self::assertArrayHasKey('name', $tag['attributes']);
            self::assertCount(1, $tag['attributes']); // Only 'name' field
        }
    }

    /**
     * Test 30: Deep nested includes (two levels).
     *
     * Tests include=author.articles to verify that we can include the author
     * and then include all articles by that author.
     */
    public function testDeepNestedIncludes(): void
    {
        // Create author
        $author = new Author();
        $author->setName('John Doe');
        $author->setEmail('john@example.com');
        $this->em->persist($author);

        // Create multiple articles by same author
        $article1 = new Article();
        $article1->setTitle('First Article');
        $article1->setContent('Content 1');
        $article1->setAuthor($author);
        $this->em->persist($article1);

        $article2 = new Article();
        $article2->setTitle('Second Article');
        $article2->setContent('Content 2');
        $article2->setAuthor($author);
        $this->em->persist($article2);

        $article3 = new Article();
        $article3->setTitle('Third Article');
        $article3->setContent('Content 3');
        $article3->setAuthor($author);
        $this->em->persist($article3);

        $this->em->flush();
        $this->em->clear();

        // Request collection with nested include: author.articles
        // This should include the author and all their articles
        $request = $this->createJsonApiGetRequest('GET', '/api/articles?include=author.articles');
        $response = ($this->controller)($request, 'articles');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        self::assertArrayHasKey('included', $document);

        // Should include authors and articles
        $includedTypes = array_column($document['included'], 'type');
        self::assertContains('authors', $includedTypes);
        self::assertContains('articles', $includedTypes);

        // Verify we have the expected resources
        $authors = array_filter($document['included'], fn ($item) => $item['type'] === 'authors');
        $articles = array_filter($document['included'], fn ($item) => $item['type'] === 'articles');

        self::assertCount(1, $authors); // One author
        self::assertGreaterThanOrEqual(2, count($articles)); // At least 2 other articles by same author
    }

    /**
     * Test 31: Collection with resource type containing underscore (category_synonyms).
     *
     * Tests that resources with underscores in their type names work correctly
     * with filtering, sorting, and pagination. This is important for verifying
     * that naming conventions (snake_case vs kebab-case) are handled properly.
     */
    public function testCollectionWithUnderscoreResourceType(): void
    {
        // Create a category first
        $category = new Category();
        $category->setName('Electronics');
        $this->em->persist($category);
        $this->em->flush();

        // Create multiple category synonyms
        $synonym1 = new CategorySynonym();
        $synonym1->setName('Electronic Devices');
        $synonym1->setCategory($category);
        $synonym1->setIsMain(true);
        $synonym1->setIsActive(true);
        $this->em->persist($synonym1);

        $synonym2 = new CategorySynonym();
        $synonym2->setName('Electronics & Gadgets');
        $synonym2->setCategory($category);
        $synonym2->setIsActive(true);
        $this->em->persist($synonym2);

        $synonym3 = new CategorySynonym();
        $synonym3->setName('Tech Products');
        $synonym3->setCategory($category);
        $synonym3->setIsActive(false);
        $this->em->persist($synonym3);

        $this->em->flush();
        $this->em->clear();

        // Test 1: Basic collection fetch
        $request = $this->createJsonApiGetRequest('GET', '/api/category_synonyms');
        $response = ($this->controller)($request, 'category_synonyms');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        self::assertArrayHasKey('data', $document);
        self::assertIsArray($document['data']);
        self::assertCount(3, $document['data']);

        // Verify resource type
        foreach ($document['data'] as $resource) {
            self::assertSame('category_synonyms', $resource['type']);
            self::assertArrayHasKey('attributes', $resource);
            self::assertArrayHasKey('name', $resource['attributes']);
        }

        // Test 2: Filtering by isActive
        $request = $this->createJsonApiGetRequest('GET', '/api/category_synonyms?filter[isActive][eq]=true');
        $response = ($this->controller)($request, 'category_synonyms');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);
        self::assertCount(2, $document['data']); // Only active synonyms

        // Test 3: Filtering by isMain
        $request = $this->createJsonApiGetRequest('GET', '/api/category_synonyms?filter[isMain][eq]=true');
        $response = ($this->controller)($request, 'category_synonyms');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);
        self::assertCount(1, $document['data']); // Only main synonym
        self::assertSame('Electronic Devices', $document['data'][0]['attributes']['name']);

        // Test 4: Sorting by name
        $request = $this->createJsonApiGetRequest('GET', '/api/category_synonyms?sort=name');
        $response = ($this->controller)($request, 'category_synonyms');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);
        $names = array_column(array_column($document['data'], 'attributes'), 'name');
        self::assertSame(['Electronic Devices', 'Electronics & Gadgets', 'Tech Products'], $names);

        // Test 5: Include category relationship
        $request = $this->createJsonApiGetRequest('GET', '/api/category_synonyms?include=category');
        $response = ($this->controller)($request, 'category_synonyms');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        self::assertArrayHasKey('included', $document);
        self::assertCount(1, $document['included']); // One category
        self::assertSame('categories', $document['included'][0]['type']);
        self::assertSame('Electronics', $document['included'][0]['attributes']['name']);

        // Verify relationships are present
        foreach ($document['data'] as $resource) {
            self::assertArrayHasKey('relationships', $resource);
            self::assertArrayHasKey('category', $resource['relationships']);
        }

        // Test 6: Pagination
        $request = $this->createJsonApiGetRequest('GET', '/api/category_synonyms?page[size]=2&page[number]=1');
        $response = ($this->controller)($request, 'category_synonyms');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);
        self::assertCount(2, $document['data']); // First page with 2 items
    }

    /**
     * Helper method to create JSON:API GET request.
     *
     * @param  string               $method HTTP method
     * @param  string               $uri    Request URI
     * @param  array<string, mixed> $query  Query parameters
     * @return Request
     */
    private function createJsonApiGetRequest(string $method, string $uri, array $query = []): Request
    {
        return Request::create(
            $uri,
            $method,
            $query,
            [],
            [],
            ['HTTP_ACCEPT' => MediaType::JSON_API]
        );
    }
}
