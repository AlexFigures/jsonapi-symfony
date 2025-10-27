<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Http\Controller;

use AlexFigures\Symfony\Http\Controller\OptionsController;
use AlexFigures\Symfony\Resource\Definition\ResourceOperation;
use AlexFigures\Symfony\Resource\Metadata\ResourceMetadata;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Article;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Integration tests for OptionsController.
 *
 * Tests OPTIONS request handling for different endpoints with various operation configurations.
 */
final class OptionsControllerTest extends TestCase
{
    private OptionsController $controller;

    /** @var array<string, ResourceMetadata> */
    private array $resources = [];

    protected function setUp(): void
    {
        $this->resources = [];
        $this->controller = new OptionsController($this->createRegistry());
    }

    /**
     * Test OPTIONS for collection endpoint with all operations allowed.
     */
    public function testCollectionOptionsWithAllOperations(): void
    {
        $this->registerResource('articles', [
            ResourceOperation::INDEX,
            ResourceOperation::CREATE,
        ]);

        $response = $this->controller->collection('articles');

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertSame('', $response->getContent());
        self::assertTrue($response->headers->has('Allow'));

        $allowHeader = $response->headers->get('Allow');
        self::assertNotNull($allowHeader);

        $methods = array_map('trim', explode(',', $allowHeader));
        sort($methods);

        self::assertSame(['GET', 'HEAD', 'OPTIONS', 'POST'], $methods);
    }

    /**
     * Test OPTIONS for collection endpoint with only INDEX operation.
     */
    public function testCollectionOptionsWithIndexOnly(): void
    {
        $this->registerResource('articles', [
            ResourceOperation::INDEX,
        ]);

        $response = $this->controller->collection('articles');

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $allowHeader = $response->headers->get('Allow');
        self::assertNotNull($allowHeader);

        $methods = array_map('trim', explode(',', $allowHeader));
        sort($methods);

        self::assertSame(['GET', 'HEAD', 'OPTIONS'], $methods);
    }

    /**
     * Test OPTIONS for collection endpoint with only CREATE operation.
     */
    public function testCollectionOptionsWithCreateOnly(): void
    {
        $this->registerResource('articles', [
            ResourceOperation::CREATE,
        ]);

        $response = $this->controller->collection('articles');

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $allowHeader = $response->headers->get('Allow');
        self::assertNotNull($allowHeader);

        $methods = array_map('trim', explode(',', $allowHeader));
        sort($methods);

        self::assertSame(['OPTIONS', 'POST'], $methods);
    }

    /**
     * Test OPTIONS for resource endpoint with all operations allowed.
     */
    public function testResourceOptionsWithAllOperations(): void
    {
        $this->registerResource('articles', [
            ResourceOperation::SHOW,
            ResourceOperation::UPDATE,
            ResourceOperation::DELETE,
        ]);

        $response = $this->controller->resource('articles');

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $allowHeader = $response->headers->get('Allow');
        self::assertNotNull($allowHeader);

        $methods = array_map('trim', explode(',', $allowHeader));
        sort($methods);

        self::assertSame(['DELETE', 'GET', 'HEAD', 'OPTIONS', 'PATCH'], $methods);
    }

    /**
     * Test OPTIONS for resource endpoint with only SHOW operation.
     */
    public function testResourceOptionsWithShowOnly(): void
    {
        $this->registerResource('articles', [
            ResourceOperation::SHOW,
        ]);

        $response = $this->controller->resource('articles');

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $allowHeader = $response->headers->get('Allow');
        self::assertNotNull($allowHeader);

        $methods = array_map('trim', explode(',', $allowHeader));
        sort($methods);

        self::assertSame(['GET', 'HEAD', 'OPTIONS'], $methods);
    }

    /**
     * Test OPTIONS for resource endpoint with UPDATE and DELETE only.
     */
    public function testResourceOptionsWithUpdateAndDeleteOnly(): void
    {
        $this->registerResource('articles', [
            ResourceOperation::UPDATE,
            ResourceOperation::DELETE,
        ]);

        $response = $this->controller->resource('articles');

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $allowHeader = $response->headers->get('Allow');
        self::assertNotNull($allowHeader);

        $methods = array_map('trim', explode(',', $allowHeader));
        sort($methods);

        self::assertSame(['DELETE', 'OPTIONS', 'PATCH'], $methods);
    }

    /**
     * Test OPTIONS for related resource endpoint.
     */
    public function testRelatedOptionsWithShowOperation(): void
    {
        $this->registerResource('articles', [
            ResourceOperation::SHOW,
        ]);

        $response = $this->controller->related('articles');

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $allowHeader = $response->headers->get('Allow');
        self::assertNotNull($allowHeader);

        $methods = array_map('trim', explode(',', $allowHeader));
        sort($methods);

        self::assertSame(['GET', 'HEAD', 'OPTIONS'], $methods);
    }

    /**
     * Test OPTIONS for related resource endpoint without SHOW operation.
     */
    public function testRelatedOptionsWithoutShowOperation(): void
    {
        $this->registerResource('articles', [
            ResourceOperation::INDEX,
            ResourceOperation::CREATE,
        ]);

        $response = $this->controller->related('articles');

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $allowHeader = $response->headers->get('Allow');
        self::assertNotNull($allowHeader);

        $methods = array_map('trim', explode(',', $allowHeader));
        sort($methods);

        // Only OPTIONS is allowed when SHOW is not present
        self::assertSame(['OPTIONS'], $methods);
    }

    /**
     * Test OPTIONS for relationship endpoint with SHOW operation only.
     */
    public function testRelationshipOptionsWithShowOnly(): void
    {
        $this->registerResource('articles', [
            ResourceOperation::SHOW,
        ]);

        $response = $this->controller->relationship('articles');

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $allowHeader = $response->headers->get('Allow');
        self::assertNotNull($allowHeader);

        $methods = array_map('trim', explode(',', $allowHeader));
        sort($methods);

        self::assertSame(['GET', 'HEAD', 'OPTIONS'], $methods);
    }

    /**
     * Test OPTIONS for relationship endpoint with UPDATE operation only.
     */
    public function testRelationshipOptionsWithUpdateOnly(): void
    {
        $this->registerResource('articles', [
            ResourceOperation::UPDATE,
        ]);

        $response = $this->controller->relationship('articles');

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $allowHeader = $response->headers->get('Allow');
        self::assertNotNull($allowHeader);

        $methods = array_map('trim', explode(',', $allowHeader));
        sort($methods);

        self::assertSame(['DELETE', 'OPTIONS', 'PATCH', 'POST'], $methods);
    }

    /**
     * Test OPTIONS for relationship endpoint with both SHOW and UPDATE operations.
     */
    public function testRelationshipOptionsWithShowAndUpdate(): void
    {
        $this->registerResource('articles', [
            ResourceOperation::SHOW,
            ResourceOperation::UPDATE,
        ]);

        $response = $this->controller->relationship('articles');

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $allowHeader = $response->headers->get('Allow');
        self::assertNotNull($allowHeader);

        $methods = array_map('trim', explode(',', $allowHeader));
        sort($methods);

        self::assertSame(['DELETE', 'GET', 'HEAD', 'OPTIONS', 'PATCH', 'POST'], $methods);
    }

    /**
     * Helper method to register a resource with specific operations.
     *
     * @param list<ResourceOperation> $operations
     */
    private function registerResource(string $type, array $operations): void
    {
        $metadata = new ResourceMetadata(
            type: $type,
            class: Article::class,
            attributes: [],
            relationships: [],
            allowedOperations: $operations
        );

        $this->resources[$type] = $metadata;
        $this->controller = new OptionsController($this->createRegistry());
    }

    private function createRegistry(): ResourceRegistryInterface
    {
        $resources = $this->resources;

        return new class ($resources) implements ResourceRegistryInterface {
            /**
             * @param array<string, ResourceMetadata> $resources
             */
            public function __construct(private array $resources)
            {
            }

            public function getByType(string $type): ResourceMetadata
            {
                if (!isset($this->resources[$type])) {
                    throw new \LogicException(sprintf('Unknown resource type "%s".', $type));
                }

                return $this->resources[$type];
            }

            public function hasType(string $type): bool
            {
                return isset($this->resources[$type]);
            }

            public function getByClass(string $class): ?ResourceMetadata
            {
                foreach ($this->resources as $metadata) {
                    if ($metadata->class === $class) {
                        return $metadata;
                    }
                }

                return null;
            }

            public function all(): array
            {
                return array_values($this->resources);
            }
        };
    }
}
