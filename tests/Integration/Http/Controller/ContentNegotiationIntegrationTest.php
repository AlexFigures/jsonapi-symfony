<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Http\Controller;

use AlexFigures\Symfony\Bridge\Symfony\EventSubscriber\ContentNegotiationSubscriber;
use AlexFigures\Symfony\Filter\Parser\FilterParser;
use AlexFigures\Symfony\Http\Controller\CollectionController;
use AlexFigures\Symfony\Http\Controller\CreateResourceController;
use AlexFigures\Symfony\Http\Document\DocumentBuilder;
use AlexFigures\Symfony\Http\Error\ErrorBuilder;
use AlexFigures\Symfony\Http\Error\ErrorMapper;
use AlexFigures\Symfony\Http\Link\LinkGenerator;
use AlexFigures\Symfony\Http\Negotiation\MediaType;
use AlexFigures\Symfony\Http\Negotiation\MediaTypePolicy;
use AlexFigures\Symfony\Http\Negotiation\MediaTypePolicyProviderInterface;
use AlexFigures\Symfony\Http\Request\FilteringWhitelist;
use AlexFigures\Symfony\Http\Request\PaginationConfig;
use AlexFigures\Symfony\Http\Request\QueryParser;
use AlexFigures\Symfony\Http\Request\SortingWhitelist;
use AlexFigures\Symfony\Http\Validation\ConstraintViolationMapper;
use AlexFigures\Symfony\Http\Write\ChangeSetFactory;
use AlexFigures\Symfony\Http\Write\InputDocumentValidator;
use AlexFigures\Symfony\Http\Write\WriteConfig;
use AlexFigures\Symfony\Tests\Integration\DoctrineIntegrationTestCase;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Tag;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Integration tests for Content Negotiation (JSON:API Status Compliance A1-A5).
 *
 * Tests HTTP 415 and 406 status codes for Content-Type and Accept header validation.
 *
 * Spec Requirements:
 * - A1: 415 for Content-Type with unsupported parameters (except ext/profile)
 * - A2: 415 for unsupported ext URI in Content-Type
 * - A3: 406 for invalid Accept parameters
 * - A4: 406 when all ext values in Accept are unsupported
 * - A5: Profiles applied/unknown ignored, Vary: Accept set
 */
final class ContentNegotiationIntegrationTest extends DoctrineIntegrationTestCase
{
    private CollectionController $collectionController;
    private CreateResourceController $createController;
    private LinkGenerator $linkGenerator;
    private ContentNegotiationSubscriber $contentNegotiationSubscriber;

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
        foreach (['tags', 'articles', 'authors'] as $type) {
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
        $this->collectionController = new CollectionController(
            $this->registry,
            $this->repository,
            $queryParser,
            $documentBuilder
        );

        // Set up CreateResourceController
        $writeConfig = new WriteConfig(true, []);
        $inputValidator = new InputDocumentValidator($this->registry, $writeConfig, $errorMapper);
        $changeSetFactory = new ChangeSetFactory($this->registry);

        $this->createController = new CreateResourceController(
            $this->registry,
            $inputValidator,
            $changeSetFactory,
            $this->validatingProcessor,
            $this->transactionManager,
            $documentBuilder,
            $this->linkGenerator,
            $writeConfig,
            $errorMapper,
            $this->violationMapper,
            new EventDispatcher()
        );

        // Set up ContentNegotiationSubscriber for strict content negotiation
        $policyProvider = new class implements MediaTypePolicyProviderInterface {
            public function getPolicy(Request $request): MediaTypePolicy
            {
                return new MediaTypePolicy(
                    allowedRequestTypes: [MediaType::JSON_API],
                    negotiableResponseTypes: [MediaType::JSON_API],
                    defaultResponseType: MediaType::JSON_API,
                    enforceJsonApiParameters: true
                );
            }
        };

        $this->contentNegotiationSubscriber = new ContentNegotiationSubscriber(
            strictContentNegotiation: true,
            policyProvider: $policyProvider
        );
    }

    protected function tearDown(): void
    {
        $this->clearDatabase();
        parent::tearDown();
    }

    /**
     * Validate content negotiation before calling controller.
     * This simulates the ContentNegotiationSubscriber behavior.
     */
    private function validateContentNegotiation(Request $request): void
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $this->contentNegotiationSubscriber->onKernelRequest($event);
    }

    /**
     * Add Vary: Accept header to response.
     * This simulates the ContentNegotiationSubscriber::onKernelResponse behavior.
     */
    private function addVaryHeader(Response $response): void
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/');
        $event = new \Symfony\Component\HttpKernel\Event\ResponseEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );
        $this->contentNegotiationSubscriber->onKernelResponse($event);
    }

    /**
     * A1: 415 Unsupported Media Type for Content-Type with unsupported parameters.
     *
     * JSON:API spec requires that Content-Type MUST be exactly "application/vnd.api+json"
     * with no media type parameters except ext and profile.
     *
     * Validates:
     * - 415 status when Content-Type has charset parameter
     * - 415 status when Content-Type has other unsupported parameters
     */
    public function testContentTypeWithUnsupportedParameterReturns415(): void
    {
        $payload = [
            'data' => [
                'type' => 'tags',
                'attributes' => ['name' => 'PHP'],
            ],
        ];

        // Test with charset parameter (unsupported)
        $request = Request::create(
            '/api/tags',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/vnd.api+json; charset=utf-8'],
            json_encode($payload)
        );

        try {
            $this->validateContentNegotiation($request);
            ($this->createController)($request, 'tags');
            self::fail('Expected UnsupportedMediaTypeException (415) for Content-Type with charset parameter');
        } catch (\AlexFigures\Symfony\Http\Exception\UnsupportedMediaTypeException $e) {
            self::assertSame(415, $e->getStatusCode());
            self::assertStringContainsString('parameters other than "ext" or "profile"', $e->getMessage());
        }
    }

    /**
     * A2: 415 Unsupported Media Type for unsupported ext URI in Content-Type.
     *
     * When Content-Type includes ext parameter with unsupported URI,
     * server MUST return 415.
     *
     * NOTE: This test is currently EXPECTED TO FAIL because the bundle
     * does not yet validate ext URIs. See reports/failures.json ID:A2.
     *
     * Validates:
     * - 415 status when ext parameter references unknown extension
     */
    public function testContentTypeWithUnsupportedExtensionReturns415(): void
    {
        $payload = [
            'data' => [
                'type' => 'tags',
                'attributes' => ['name' => 'PHP'],
            ],
        ];

        $request = Request::create(
            '/api/tags',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/vnd.api+json; ext="urn:example:unsupported"'],
            json_encode($payload)
        );

        try {
            ($this->createController)($request, 'tags');
            self::fail('Expected UnsupportedMediaTypeException (415) for unsupported ext URI');
        } catch (\AlexFigures\Symfony\Http\Exception\UnsupportedMediaTypeException $e) {
            self::assertSame(415, $e->getStatusCode());
        }
    }

    /**
     * A3: 406 Not Acceptable for Accept header with unsupported parameters.
     *
     * JSON:API spec requires that Accept MUST be "application/vnd.api+json"
     * with no media type parameters except ext and profile.
     *
     * Validates:
     * - 406 status when Accept has charset parameter
     * - 406 status when Accept has other unsupported parameters
     */
    public function testAcceptHeaderWithUnsupportedParameterReturns406(): void
    {
        // Create a tag for the GET request
        $tag = new Tag();
        $tag->setName('PHP');
        $this->em->persist($tag);
        $this->em->flush();
        $this->em->clear();

        $request = Request::create(
            '/api/tags',
            'GET',
            [],
            [],
            [],
            ['HTTP_ACCEPT' => 'application/vnd.api+json; charset=utf-8']
        );

        try {
            $this->validateContentNegotiation($request);
            ($this->collectionController)($request, 'tags');
            self::fail('Expected NotAcceptableException (406) for Accept with charset parameter');
        } catch (\AlexFigures\Symfony\Http\Exception\NotAcceptableException $e) {
            self::assertSame(406, $e->getStatusCode());
        }
    }

    /**
     * A4: 406 Not Acceptable when all ext values in Accept are unsupported.
     *
     * When Accept includes ext parameter with unsupported URI,
     * server MUST return 406.
     *
     * NOTE: This test is currently EXPECTED TO FAIL because the bundle
     * does not yet validate ext URIs. See reports/failures.json ID:A4.
     *
     * Validates:
     * - 406 status when ext parameter references unknown extension
     */
    public function testAcceptHeaderWithUnsupportedExtensionReturns406(): void
    {
        $tag = new Tag();
        $tag->setName('PHP');
        $this->em->persist($tag);
        $this->em->flush();
        $this->em->clear();

        $request = Request::create(
            '/api/tags',
            'GET',
            [],
            [],
            [],
            ['HTTP_ACCEPT' => 'application/vnd.api+json; ext="urn:example:unsupported"']
        );

        try {
            ($this->collectionController)($request, 'tags');
            self::fail('Expected NotAcceptableException (406) for unsupported ext URI');
        } catch (\AlexFigures\Symfony\Http\Exception\NotAcceptableException $e) {
            self::assertSame(406, $e->getStatusCode());
        }
    }

    /**
     * A5: Unknown profiles are ignored, Vary: Accept header is set.
     *
     * JSON:API spec states that unknown profile URIs SHOULD be ignored.
     * Server SHOULD include Vary: Accept header in responses.
     *
     * Validates:
     * - Request with unknown profile succeeds (200 OK)
     * - Response includes Vary: Accept header
     */
    public function testAcceptHeaderWithUnknownProfileIsIgnoredAndVaryHeaderSet(): void
    {
        $tag = new Tag();
        $tag->setName('PHP');
        $this->em->persist($tag);
        $this->em->flush();
        $this->em->clear();

        $request = Request::create(
            '/api/tags',
            'GET',
            [],
            [],
            [],
            ['HTTP_ACCEPT' => 'application/vnd.api+json; profile="urn:example:unknown"']
        );

        $this->validateContentNegotiation($request);
        $response = ($this->collectionController)($request, 'tags');
        $this->addVaryHeader($response);

        // Should succeed with 200 OK (unknown profile ignored)
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        // Should include Vary: Accept header
        self::assertTrue($response->headers->has('Vary'));
        $varyHeader = $response->headers->get('Vary');
        self::assertStringContainsString('Accept', $varyHeader);
    }
}

