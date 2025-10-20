<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Http\Controller;

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
use AlexFigures\Symfony\Tests\Integration\DoctrineIntegrationTestCase;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Article;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Author;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Tag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Integration tests for Query Parameter Validation (JSON:API Status Compliance H1-H4).
 *
 * Tests HTTP 400 Bad Request for invalid query parameters.
 *
 * Spec Requirements:
 * - H1: 400 when include parameter references unsupported/unknown relationship
 * - H2: 400 when include path is invalid
 * - H3: 400 when sort parameter references unsupported field
 * - H4: 400 for non-standard/unknown JSON:API query parameters
 */
final class QueryParameterValidationTest extends DoctrineIntegrationTestCase
{
    private CollectionController $controller;
    private LinkGenerator $linkGenerator;

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

        // Add type-specific routes
        foreach (['articles', 'authors', 'tags'] as $type) {
            $routes->add("jsonapi.{$type}.index", new Route("/api/{$type}"));
            $routes->add("jsonapi.{$type}.show", new Route("/api/{$type}/{id}"));
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

        // Set up sorting whitelist
        $sortingWhitelist = new SortingWhitelist($this->registry);

        // Set up filtering whitelist
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

        // Set up CollectionController
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
     * H1: 400 Bad Request when include parameter references unsupported relationship.
     *
     * JSON:API spec (Section: Inclusion of Related Resources):
     * "If a server is unable to identify a relationship path or does not support
     * inclusion of resources from a path, it MUST respond with 400 Bad Request."
     *
     * Validates:
     * - HTTP 400 status code
     * - Response Content-Type is application/vnd.api+json
     * - Response contains "errors" array (not "error")
     * - Error object contains "status" field as string "400"
     * - Error object contains "detail" or "title" describing the issue
     * - Error object MAY contain "source.parameter" pointing to "include"
     */
    public function testIncludeUnsupportedRelationshipReturns400(): void
    {
        // Create test data
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
        $this->em->clear();

        // Request with unknown relationship
        $request = Request::create(
            '/api/articles?include=unknownRelationship',
            'GET',
            [],
            [],
            [],
            ['HTTP_ACCEPT' => MediaType::JSON_API]
        );

        try {
            $response = ($this->controller)($request, 'articles');

            // If no exception, check response directly
            self::assertSame(400, $response->getStatusCode(), 'MUST return 400 for unknown include relationship');
            self::assertSame(MediaType::JSON_API, $response->headers->get('Content-Type'), 'MUST use JSON:API media type');

            $data = json_decode($response->getContent(), true);
            self::assertIsArray($data, 'Response MUST be valid JSON');
            self::assertArrayHasKey('errors', $data, 'Error response MUST contain "errors" array');
            self::assertIsArray($data['errors'], '"errors" MUST be an array');
            self::assertNotEmpty($data['errors'], '"errors" array MUST contain at least one error object');

            $error = $data['errors'][0];
            self::assertArrayHasKey('status', $error, 'Error object MUST contain "status"');
            self::assertSame('400', $error['status'], '"status" MUST be string "400"');

            // MUST have either title or detail
            self::assertTrue(
                isset($error['title']) || isset($error['detail']),
                'Error object MUST contain "title" or "detail"'
            );

            // SHOULD point to the problematic parameter
            if (isset($error['source']['parameter'])) {
                self::assertSame('include', $error['source']['parameter'], 'Error source SHOULD point to "include" parameter');
            }

        } catch (\AlexFigures\Symfony\Http\Exception\BadRequestException $e) {
            // Exception thrown - verify it's correct
            self::assertSame(400, $e->getStatusCode(), 'MUST return 400 for unknown include relationship');
        }
    }

    /**
     * H2: 400 Bad Request when include path is invalid.
     *
     * JSON:API spec (Section: Inclusion of Related Resources):
     * "If a server is unable to identify a relationship path or does not support
     * inclusion of resources from a path, it MUST respond with 400 Bad Request."
     *
     * This tests nested relationship paths where intermediate or final segments
     * reference non-existent relationships.
     *
     * Validates:
     * - HTTP 400 status code
     * - Response Content-Type is application/vnd.api+json
     * - Response contains "errors" array
     * - Error object contains "status" field as string "400"
     * - Error object describes the invalid path
     * - Error object MAY contain "source.parameter" pointing to "include"
     */
    public function testIncludeInvalidPathReturns400(): void
    {
        // Create test data
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
        $this->em->clear();

        // Request with invalid nested path (author doesn't have 'invalidRelationship')
        $request = Request::create(
            '/api/articles?include=author.invalidRelationship',
            'GET',
            [],
            [],
            [],
            ['HTTP_ACCEPT' => MediaType::JSON_API]
        );

        try {
            $response = ($this->controller)($request, 'articles');

            // If no exception, check response directly
            self::assertSame(400, $response->getStatusCode(), 'MUST return 400 for invalid include path');
            self::assertSame(MediaType::JSON_API, $response->headers->get('Content-Type'), 'MUST use JSON:API media type');

            $data = json_decode($response->getContent(), true);
            self::assertIsArray($data, 'Response MUST be valid JSON');
            self::assertArrayHasKey('errors', $data, 'Error response MUST contain "errors" array');
            self::assertIsArray($data['errors'], '"errors" MUST be an array');
            self::assertNotEmpty($data['errors'], '"errors" array MUST contain at least one error object');

            $error = $data['errors'][0];
            self::assertArrayHasKey('status', $error, 'Error object MUST contain "status"');
            self::assertSame('400', $error['status'], '"status" MUST be string "400"');

            // MUST have either title or detail
            self::assertTrue(
                isset($error['title']) || isset($error['detail']),
                'Error object MUST contain "title" or "detail"'
            );

        } catch (\AlexFigures\Symfony\Http\Exception\BadRequestException $e) {
            // Exception thrown - verify it's correct
            self::assertSame(400, $e->getStatusCode(), 'MUST return 400 for invalid include path');
        }
    }

    /**
     * H3: 400 Bad Request when sort parameter references unsupported field.
     *
     * JSON:API spec (Section: Sorting):
     * "If the server does not support sorting as specified in the query parameter sort,
     * it MUST return 400 Bad Request."
     *
     * Validates:
     * - HTTP 400 status code
     * - Response Content-Type is application/vnd.api+json
     * - Response contains "errors" array
     * - Error object contains "status" field as string "400"
     * - Error object describes the unsupported sort field
     * - Error object MAY contain "source.parameter" pointing to "sort"
     */
    public function testSortUnsupportedFieldReturns400(): void
    {
        // Create test data
        $tag = new Tag();
        $tag->setName('PHP');
        $this->em->persist($tag);
        $this->em->flush();
        $this->em->clear();

        // Request with unsupported sort field
        $request = Request::create(
            '/api/tags?sort=unsupportedField',
            'GET',
            [],
            [],
            [],
            ['HTTP_ACCEPT' => MediaType::JSON_API]
        );

        try {
            $response = ($this->controller)($request, 'tags');

            // If no exception, check response directly
            self::assertSame(400, $response->getStatusCode(), 'MUST return 400 for unsupported sort field');
            self::assertSame(MediaType::JSON_API, $response->headers->get('Content-Type'), 'MUST use JSON:API media type');

            $data = json_decode($response->getContent(), true);
            self::assertIsArray($data, 'Response MUST be valid JSON');
            self::assertArrayHasKey('errors', $data, 'Error response MUST contain "errors" array');
            self::assertIsArray($data['errors'], '"errors" MUST be an array');
            self::assertNotEmpty($data['errors'], '"errors" array MUST contain at least one error object');

            $error = $data['errors'][0];
            self::assertArrayHasKey('status', $error, 'Error object MUST contain "status"');
            self::assertSame('400', $error['status'], '"status" MUST be string "400"');

            // MUST have either title or detail
            self::assertTrue(
                isset($error['title']) || isset($error['detail']),
                'Error object MUST contain "title" or "detail"'
            );

            // SHOULD point to the problematic parameter
            if (isset($error['source']['parameter'])) {
                self::assertSame('sort', $error['source']['parameter'], 'Error source SHOULD point to "sort" parameter');
            }

        } catch (\AlexFigures\Symfony\Http\Exception\BadRequestException $e) {
            // Exception thrown - verify it's correct
            self::assertSame(400, $e->getStatusCode(), 'MUST return 400 for unsupported sort field');
        }
    }

    /**
     * H4: 400 Bad Request for non-standard/unknown JSON:API query parameters.
     *
     * JSON:API spec (Section: Query Parameters):
     * "If a server encounters a query parameter that does not follow the naming
     * conventions above, and the server does not know how to process it as a query
     * parameter from this specification, it MUST return 400 Bad Request."
     *
     * Standard JSON:API query parameters:
     * - include, filter, sort, page, fields
     * - Extension parameters (prefixed with extension namespace)
     *
     * Any other parameter MUST result in 400 Bad Request.
     *
     * NOTE: This test is currently EXPECTED TO FAIL because the bundle
     * ignores unknown query parameters. See reports/failures.json ID:H4.
     *
     * Validates:
     * - HTTP 400 status code
     * - Response Content-Type is application/vnd.api+json
     * - Response contains "errors" array
     * - Error object contains "status" field as string "400"
     * - Error object describes the unknown parameter
     * - Error object MAY contain "source.parameter" pointing to the unknown parameter
     */
    public function testUnknownQueryParameterReturns400(): void
    {
        // Create test data
        $tag = new Tag();
        $tag->setName('PHP');
        $this->em->persist($tag);
        $this->em->flush();
        $this->em->clear();

        // Request with unknown query parameter (not in JSON:API spec)
        $request = Request::create(
            '/api/tags?unknownParam=value',
            'GET',
            [],
            [],
            [],
            ['HTTP_ACCEPT' => MediaType::JSON_API]
        );

        try {
            $response = ($this->controller)($request, 'tags');

            // If no exception, check response directly
            self::assertSame(400, $response->getStatusCode(), 'MUST return 400 for unknown query parameter');
            self::assertSame(MediaType::JSON_API, $response->headers->get('Content-Type'), 'MUST use JSON:API media type');

            $data = json_decode($response->getContent(), true);
            self::assertIsArray($data, 'Response MUST be valid JSON');
            self::assertArrayHasKey('errors', $data, 'Error response MUST contain "errors" array');
            self::assertIsArray($data['errors'], '"errors" MUST be an array');
            self::assertNotEmpty($data['errors'], '"errors" array MUST contain at least one error object');

            $error = $data['errors'][0];
            self::assertArrayHasKey('status', $error, 'Error object MUST contain "status"');
            self::assertSame('400', $error['status'], '"status" MUST be string "400"');

            // MUST have either title or detail
            self::assertTrue(
                isset($error['title']) || isset($error['detail']),
                'Error object MUST contain "title" or "detail"'
            );

            // SHOULD point to the problematic parameter
            if (isset($error['source']['parameter'])) {
                self::assertSame('unknownParam', $error['source']['parameter'], 'Error source SHOULD point to unknown parameter');
            }

        } catch (\AlexFigures\Symfony\Http\Exception\BadRequestException $e) {
            // Exception thrown - verify it's correct
            self::assertSame(400, $e->getStatusCode(), 'MUST return 400 for unknown query parameter');
        }
    }
}
