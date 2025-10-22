<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Http\Controller;

use AlexFigures\Symfony\Http\Controller\CreateResourceController;
use AlexFigures\Symfony\Http\Document\DocumentBuilder;
use AlexFigures\Symfony\Http\Error\ErrorBuilder;
use AlexFigures\Symfony\Http\Error\ErrorMapper;
use AlexFigures\Symfony\Http\Link\LinkGenerator;
use AlexFigures\Symfony\Http\Negotiation\MediaType;
use AlexFigures\Symfony\Http\Write\ChangeSetFactory;
use AlexFigures\Symfony\Http\Write\InputDocumentValidator;
use AlexFigures\Symfony\Http\Write\WriteConfig;
use AlexFigures\Symfony\Tests\Integration\DoctrineIntegrationTestCase;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\ArticleStatus;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\TypeTestEntity;
use AlexFigures\Symfony\Tests\Util\JsonApiResponseAsserts;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Uid\Uuid;

/**
 * Integration test for verifying support of various PHP/Symfony types.
 *
 * Tests that all standard Symfony normalizers are properly configured:
 * - UidNormalizer (Uuid, Ulid)
 * - BackedEnumNormalizer (PHP 8.1+ enums)
 * - DateTimeNormalizer (DateTime, DateTimeImmutable)
 * - DateTimeZoneNormalizer (DateTimeZone)
 * - DateIntervalNormalizer (DateInterval)
 * - JsonSerializableNormalizer (JsonSerializable objects)
 */
final class TypeNormalizationTest extends DoctrineIntegrationTestCase
{
    use JsonApiResponseAsserts;

    private CreateResourceController $controller;

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
        $routes->add('jsonapi.create', new Route('/api/{type}', methods: ['POST']));
        $routes->add('jsonapi.type-test-entities.show', new Route('/api/type-test-entities/{id}'));
        $routes->add('jsonapi.products.show', new Route('/api/products/{id}'));

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

        $errorBuilder = new ErrorBuilder(true);
        $errorMapper = new ErrorMapper($errorBuilder);
        $writeConfig = new WriteConfig(true, []);
        $inputValidator = new InputDocumentValidator($this->registry, $writeConfig, $errorMapper);
        $changeSetFactory = new ChangeSetFactory($this->registry);
        $eventDispatcher = new EventDispatcher();

        $this->controller = new CreateResourceController(
            $this->registry,
            $inputValidator,
            $changeSetFactory,
            $this->validatingProcessor,
            $this->transactionManager,
            $documentBuilder,
            $linkGenerator,
            $writeConfig,
            $errorMapper,
            $this->violationMapper,
            $eventDispatcher
        );
    }

    protected function tearDown(): void
    {
        $this->clearDatabase();
        parent::tearDown();
    }

    /**
     * Test: Create resource with BackedEnum (ArticleStatus).
     *
     * Validates that BackedEnumNormalizer correctly denormalizes
     * string values to enum cases.
     */
    public function testCreateResourceWithBackedEnum(): void
    {
        $payload = [
            'data' => [
                'type' => 'type-test-entities',
                'attributes' => [
                    'name' => 'Test Entity',
                    'status' => 'published', // String value for enum
                ],
            ],
        ];

        $request = $this->createJsonApiRequest('POST', '/api/type-test-entities', $payload);
        $response = ($this->controller)($request, 'type-test-entities');

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        $document = $this->decode($response);
        $entityId = $document['data']['id'];

        // Verify enum is serialized back to string
        self::assertSame('published', $document['data']['attributes']['status']);

        // Verify persistence
        $this->em->clear();
        $entity = $this->em->find(TypeTestEntity::class, $entityId);
        self::assertInstanceOf(TypeTestEntity::class, $entity);
        self::assertSame(ArticleStatus::PUBLISHED, $entity->getStatus());
    }

    /**
     * Test: Create resource with Uuid.
     *
     * Validates that UidNormalizer correctly denormalizes
     * string UUIDs to Uuid objects.
     */
    public function testCreateResourceWithUuid(): void
    {
        $testUuid = Uuid::v4();

        $payload = [
            'data' => [
                'type' => 'type-test-entities',
                'attributes' => [
                    'name' => 'Test Entity',
                    'uuid' => $testUuid->toRfc4122(), // String UUID
                ],
            ],
        ];

        $request = $this->createJsonApiRequest('POST', '/api/type-test-entities', $payload);
        $response = ($this->controller)($request, 'type-test-entities');

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        $document = $this->decode($response);
        $entityId = $document['data']['id'];

        // Verify UUID is serialized back to string
        self::assertSame($testUuid->toRfc4122(), $document['data']['attributes']['uuid']);

        // Verify persistence
        $this->em->clear();
        $entity = $this->em->find(TypeTestEntity::class, $entityId);
        self::assertInstanceOf(TypeTestEntity::class, $entity);
        self::assertInstanceOf(Uuid::class, $entity->getUuid());
        self::assertSame($testUuid->toRfc4122(), $entity->getUuid()->toRfc4122());
    }

    /**
     * Test: Create resource with DateTimeImmutable.
     *
     * Validates that DateTimeNormalizer correctly denormalizes
     * ISO 8601 strings to DateTimeImmutable objects.
     */
    public function testCreateResourceWithDateTimeImmutable(): void
    {
        $payload = [
            'data' => [
                'type' => 'type-test-entities',
                'attributes' => [
                    'name' => 'Test Entity',
                    'publishedAt' => '2024-01-15T10:30:00+00:00', // ISO 8601
                ],
            ],
        ];

        $request = $this->createJsonApiRequest('POST', '/api/type-test-entities', $payload);
        $response = ($this->controller)($request, 'type-test-entities');

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        $document = $this->decode($response);
        $entityId = $document['data']['id'];

        // Verify persistence
        $this->em->clear();
        $entity = $this->em->find(TypeTestEntity::class, $entityId);
        self::assertInstanceOf(TypeTestEntity::class, $entity);
        self::assertInstanceOf(\DateTimeImmutable::class, $entity->getPublishedAt());
        self::assertSame('2024-01-15T10:30:00+00:00', $entity->getPublishedAt()->format('c'));
    }

    /**
     * Test: Create resource with DateTimeZone.
     *
     * Validates that DateTimeZoneNormalizer correctly denormalizes
     * timezone strings to DateTimeZone objects.
     */
    public function testCreateResourceWithDateTimeZone(): void
    {
        $payload = [
            'data' => [
                'type' => 'type-test-entities',
                'attributes' => [
                    'name' => 'Test Entity',
                    'timezone' => 'Europe/Paris',
                ],
            ],
        ];

        $request = $this->createJsonApiRequest('POST', '/api/type-test-entities', $payload);
        $response = ($this->controller)($request, 'type-test-entities');

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        $document = $this->decode($response);
        $entityId = $document['data']['id'];

        // Verify persistence
        $this->em->clear();
        $entity = $this->em->find(TypeTestEntity::class, $entityId);
        self::assertInstanceOf(TypeTestEntity::class, $entity);
        self::assertInstanceOf(\DateTimeZone::class, $entity->getTimezone());
        self::assertSame('Europe/Paris', $entity->getTimezone()->getName());
    }

    /**
     * Test: Create resource with DateInterval.
     *
     * Validates that DateIntervalNormalizer correctly denormalizes
     * ISO 8601 duration strings to DateInterval objects.
     */
    public function testCreateResourceWithDateInterval(): void
    {
        $payload = [
            'data' => [
                'type' => 'type-test-entities',
                'attributes' => [
                    'name' => 'Test Entity',
                    'duration' => 'P1Y2M3DT4H5M6S', // ISO 8601 duration
                ],
            ],
        ];

        $request = $this->createJsonApiRequest('POST', '/api/type-test-entities', $payload);
        $response = ($this->controller)($request, 'type-test-entities');

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        $document = $this->decode($response);
        $entityId = $document['data']['id'];

        // Verify persistence
        $this->em->clear();
        $entity = $this->em->find(TypeTestEntity::class, $entityId);
        self::assertInstanceOf(TypeTestEntity::class, $entity);
        self::assertInstanceOf(\DateInterval::class, $entity->getDuration());
        self::assertSame(1, $entity->getDuration()->y);
        self::assertSame(2, $entity->getDuration()->m);
        self::assertSame(3, $entity->getDuration()->d);
        self::assertSame(4, $entity->getDuration()->h);
        self::assertSame(5, $entity->getDuration()->i);
        self::assertSame(6, $entity->getDuration()->s);
    }

    /**
     * Test: Type coercion - int to float (safe conversion).
     *
     * Validates that integers are automatically coerced to floats
     * when the property expects a float type.
     */
    public function testTypeCoercionIntToFloat(): void
    {
        // Create a TypeTestEntity with rating as float, but send int
        $payload = [
            'data' => [
                'type' => 'type-test-entities',
                'attributes' => [
                    'name' => 'Test Entity',
                    'rating' => 5, // int instead of float
                ],
            ],
        ];

        $request = $this->createJsonApiRequest('POST', '/api/type-test-entities', $payload);

        // This should NOT fail - int should be coerced to float
        $response = ($this->controller)($request, 'type-test-entities');

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        self::assertSame('type-test-entities', $responseData['data']['type']);

        // Verify the entity was created with the correct type
        $entityId = $responseData['data']['id'];
        $entity = $this->em->find(TypeTestEntity::class, $entityId);

        self::assertNotNull($entity);
        self::assertSame(5.0, $entity->getRating());
        self::assertIsFloat($entity->getRating());
    }

    /**
     * Test: Type coercion - int to string (safe conversion).
     *
     * Validates that integers are automatically coerced to strings
     * when the property expects a string type.
     */
    public function testTypeCoercionIntToString(): void
    {
        $payload = [
            'data' => [
                'type' => 'type-test-entities',
                'attributes' => [
                    'name' => 12345, // int instead of string
                ],
            ],
        ];

        $request = $this->createJsonApiRequest('POST', '/api/type-test-entities', $payload);
        $response = ($this->controller)($request, 'type-test-entities');

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        $document = $this->decode($response);

        // Verify the int was coerced to string
        self::assertSame('12345', $document['data']['attributes']['name']);
    }

    /**
     * Test: Type coercion - float to string (safe conversion).
     *
     * Validates that floats are automatically coerced to strings
     * when the property expects a string type.
     */
    public function testTypeCoercionFloatToString(): void
    {
        $payload = [
            'data' => [
                'type' => 'type-test-entities',
                'attributes' => [
                    'name' => 123.45, // float instead of string
                ],
            ],
        ];

        $request = $this->createJsonApiRequest('POST', '/api/type-test-entities', $payload);
        $response = ($this->controller)($request, 'type-test-entities');

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        $document = $this->decode($response);

        // Verify the float was coerced to string
        self::assertSame('123.45', $document['data']['attributes']['name']);
    }

    private function createJsonApiRequest(string $method, string $uri, array $payload): Request
    {
        return Request::create(
            $uri,
            $method,
            [],
            [],
            [],
            ['CONTENT_TYPE' => MediaType::JSON_API],
            json_encode($payload, \JSON_THROW_ON_ERROR)
        );
    }
}
