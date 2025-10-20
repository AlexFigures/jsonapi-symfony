<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Http\Controller;

use AlexFigures\Symfony\Filter\Parser\FilterParser;
use AlexFigures\Symfony\Http\Controller\CreateResourceController;
use AlexFigures\Symfony\Http\Controller\ResourceController;
use AlexFigures\Symfony\Http\Document\DocumentBuilder;
use AlexFigures\Symfony\Http\Error\ErrorBuilder;
use AlexFigures\Symfony\Http\Error\ErrorMapper;
use AlexFigures\Symfony\Http\Link\LinkGenerator;
use AlexFigures\Symfony\Http\Negotiation\MediaType;
use AlexFigures\Symfony\Http\Request\FilteringWhitelist;
use AlexFigures\Symfony\Http\Request\PaginationConfig;
use AlexFigures\Symfony\Http\Request\QueryParser;
use AlexFigures\Symfony\Http\Request\SortingWhitelist;
use AlexFigures\Symfony\Http\Validation\ConstraintViolationMapper;
use AlexFigures\Symfony\Http\Write\ChangeSetFactory;
use AlexFigures\Symfony\Http\Write\InputDocumentValidator;
use AlexFigures\Symfony\Http\Write\WriteConfig;
use AlexFigures\Symfony\Tests\Integration\DoctrineIntegrationTestCase;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Author;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Integration tests for Error Response Structure (JSON:API Status Compliance I1-I3).
 *
 * Tests error response format and structure.
 *
 * Spec Requirements:
 * - I1: Error responses MUST contain top-level "errors" array
 * - I2: Error object "status" field MUST be string (not integer)
 * - I3: Error objects SHOULD include "links.about" or "links.type"
 */
final class ErrorResponseStructureTest extends DoctrineIntegrationTestCase
{
    private CreateResourceController $createController;
    private ResourceController $resourceController;
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

        // Set up ResourceController (use repository from base class)
        $this->resourceController = new ResourceController(
            $this->registry,
            $this->repository,
            $queryParser,
            $documentBuilder,
            $errorMapper
        );

        // Set up write dependencies
        $changeSetFactory = new ChangeSetFactory($this->registry);
        $writeConfig = new WriteConfig(allowRelationshipWrites: false, clientIdAllowed: []);
        $inputValidator = new InputDocumentValidator(
            $this->registry,
            $writeConfig,
            $errorMapper
        );
        $violationMapper = new ConstraintViolationMapper($this->registry, $errorMapper);

        // Set up CreateResourceController (use processor and transactionManager from base class)
        $this->createController = new CreateResourceController(
            $this->registry,
            $inputValidator,
            $changeSetFactory,
            $this->processor,
            $this->transactionManager,
            $documentBuilder,
            $this->linkGenerator,
            $writeConfig,
            $errorMapper,
            $violationMapper,
            new EventDispatcher()
        );
    }

    protected function tearDown(): void
    {
        $this->clearDatabase();
        parent::tearDown();
    }

    /**
     * I1: Error responses MUST contain top-level "errors" array.
     *
     * JSON:API spec (Section: Error Objects):
     * "Error objects MUST be returned as an array keyed by errors in the top level
     * of a JSON:API document."
     *
     * "A server MUST respond to an unsuccessful request with an appropriate HTTP status
     * code. It SHOULD also provide an error response that includes additional details."
     *
     * Validates:
     * - Response has appropriate HTTP error status code (4xx or 5xx)
     * - Response Content-Type is application/vnd.api+json
     * - Response body is valid JSON
     * - Response contains top-level "errors" key (not "error")
     * - "errors" value is an array (not a single object)
     * - "errors" array contains at least one error object
     * - Response MUST NOT contain "data" key when "errors" is present
     * - Each error object is a JSON object (not a string or primitive)
     */
    public function testErrorResponseContainsErrorsArray(): void
    {
        // Request non-existent resource to trigger 404
        $request = Request::create(
            '/api/articles/non-existent-id',
            'GET',
            [],
            [],
            [],
            ['HTTP_ACCEPT' => MediaType::JSON_API]
        );

        try {
            ($this->resourceController)($request, 'articles', 'non-existent-id');
            self::fail('Expected NotFoundException (404)');
        } catch (\AlexFigures\Symfony\Http\Exception\NotFoundException $e) {
            // Exception thrown - now check error response structure
            $response = $this->handleException($request, $e);

            // MUST have error status code
            self::assertGreaterThanOrEqual(400, $response->getStatusCode(), 'Error response MUST have 4xx or 5xx status');
            self::assertLessThan(600, $response->getStatusCode(), 'Error response MUST have 4xx or 5xx status');

            // MUST use JSON:API media type
            self::assertSame(MediaType::JSON_API, $response->headers->get('Content-Type'), 'Error response MUST use JSON:API media type');

            // MUST be valid JSON
            $data = json_decode($response->getContent(), true);
            self::assertIsArray($data, 'Error response MUST be valid JSON');

            // I1: MUST have "errors" array (not "error")
            self::assertArrayHasKey('errors', $data, 'Error response MUST contain "errors" key (not "error")');
            self::assertIsArray($data['errors'], '"errors" MUST be an array (not a single object)');
            self::assertNotEmpty($data['errors'], '"errors" array MUST contain at least one error object');

            // MUST NOT have "data" when "errors" is present
            self::assertArrayNotHasKey('data', $data, 'Error response MUST NOT contain "data" when "errors" is present');

            // Verify each error is an object
            foreach ($data['errors'] as $index => $error) {
                self::assertIsArray($error, "Error at index {$index} MUST be an object (not string or primitive)");
            }
        }
    }

    /**
     * I2: Error object "status" field MUST be string (not integer).
     *
     * JSON:API spec (Section: Error Objects):
     * "status: the HTTP status code applicable to this problem, expressed as a string value."
     *
     * This is a common mistake - many implementations use integer status codes,
     * but JSON:API explicitly requires strings.
     *
     * Validates:
     * - Error object contains "status" member
     * - "status" is a string type (e.g., "422", not 422)
     * - "status" value matches the HTTP response status code
     * - "status" contains only digits (no extra text)
     * - All errors in the array have consistent status values
     */
    public function testErrorStatusFieldIsString(): void
    {
        // Create a request
        $request = Request::create(
            '/api/articles/test-id',
            'GET',
            [],
            [],
            [],
            ['HTTP_ACCEPT' => MediaType::JSON_API]
        );

        // Create exception with multiple errors to test consistency
        $errorBuilder = new ErrorBuilder(true);
        $errorMapper = new ErrorMapper($errorBuilder);

        $errors = [
            $errorMapper->notFound('Resource not found'),
            $errorMapper->notFound('Another resource not found'),
        ];

        $exception = new \AlexFigures\Symfony\Http\Exception\NotFoundException(
            'Multiple resources not found',
            $errors
        );

        // Handle exception through JsonApiExceptionListener
        $response = $this->handleException($request, $exception);
        $httpStatusCode = $response->getStatusCode();
        $data = json_decode($response->getContent(), true);

        self::assertArrayHasKey('errors', $data);
        self::assertNotEmpty($data['errors']);

        foreach ($data['errors'] as $index => $error) {
            // I2: "status" MUST be string
            self::assertArrayHasKey('status', $error, "Error at index {$index} MUST contain 'status' member");
            self::assertIsString($error['status'], "Error 'status' MUST be a string (e.g., '404'), not integer (404)");

            // MUST match HTTP status code
            self::assertSame((string) $httpStatusCode, $error['status'], "Error 'status' MUST match HTTP response status code");

            // MUST be numeric string
            self::assertMatchesRegularExpression('/^\d{3}$/', $error['status'], "Error 'status' MUST be a 3-digit numeric string");
        }

        // All errors SHOULD have consistent status values
        $statuses = array_column($data['errors'], 'status');
        $uniqueStatuses = array_unique($statuses);
        self::assertCount(1, $uniqueStatuses, 'All errors in a single response SHOULD have the same status value');
    }

    /**
     * I3: Error objects SHOULD include "links.about" or "links.type".
     *
     * JSON:API spec (Section: Error Objects):
     * "links: a links object that MAY contain the following members:
     *  - about: a link that leads to further details about this particular occurrence of the problem.
     *  - type: a link that identifies the type of error that this particular error is an instance of."
     *
     * While this is a SHOULD (not MUST), it's a best practice for production APIs
     * to provide links to documentation or error tracking systems.
     *
     * NOTE: This test is currently EXPECTED TO FAIL because the bundle
     * does not yet support error links configuration. See reports/failures.json ID:I3.
     *
     * Validates:
     * - Error object MAY contain "links" member
     * - If "links" exists, it SHOULD contain "about" or "type"
     * - "about" link SHOULD be a valid URI (absolute or relative)
     * - "type" link SHOULD be a valid URI pointing to error type documentation
     * - Links SHOULD be strings (not objects with href/meta)
     */
    public function testErrorLinksIncludeAboutOrType(): void
    {
        // Request non-existent resource to trigger 404
        $request = Request::create(
            '/api/articles/non-existent-id',
            'GET',
            [],
            [],
            [],
            ['HTTP_ACCEPT' => MediaType::JSON_API]
        );

        try {
            ($this->resourceController)($request, 'articles', 'non-existent-id');
            self::fail('Expected NotFoundException (404)');
        } catch (\AlexFigures\Symfony\Http\Exception\NotFoundException $e) {
            // Exception thrown - now check error response structure
            $response = $this->handleException($request, $e);
            $data = json_decode($response->getContent(), true);

            self::assertArrayHasKey('errors', $data);
            $error = $data['errors'][0];

            // I3: SHOULD have "links" with "about" or "type"
            self::assertArrayHasKey('links', $error, 'Error object SHOULD contain "links" member');
            self::assertIsArray($error['links'], 'Error "links" MUST be an object');

            self::assertTrue(
                isset($error['links']['about']) || isset($error['links']['type']),
                'Error "links" SHOULD contain "about" or "type" member'
            );

            // Verify "about" link if present
            if (isset($error['links']['about'])) {
                self::assertIsString($error['links']['about'], '"links.about" SHOULD be a string URI');
                self::assertNotEmpty($error['links']['about'], '"links.about" SHOULD not be empty');

                // SHOULD be a valid URI (absolute or relative)
                $isAbsoluteUri = (bool) preg_match('/^https?:\/\//', $error['links']['about']);
                $isRelativeUri = (bool) preg_match('/^\//', $error['links']['about']);
                self::assertTrue(
                    $isAbsoluteUri || $isRelativeUri,
                    '"links.about" SHOULD be a valid absolute or relative URI'
                );
            }

            // Verify "type" link if present
            if (isset($error['links']['type'])) {
                self::assertIsString($error['links']['type'], '"links.type" SHOULD be a string URI');
                self::assertNotEmpty($error['links']['type'], '"links.type" SHOULD not be empty');

                // SHOULD be a valid URI pointing to error type documentation
                $isAbsoluteUri = (bool) preg_match('/^https?:\/\//', $error['links']['type']);
                $isRelativeUri = (bool) preg_match('/^\//', $error['links']['type']);
                self::assertTrue(
                    $isAbsoluteUri || $isRelativeUri,
                    '"links.type" SHOULD be a valid absolute or relative URI'
                );
            }
        }
    }

}
