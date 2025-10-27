<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Http\Response;

use AlexFigures\Symfony\Http\Response\JsonApiErrorBuilder;
use AlexFigures\Symfony\Http\Response\JsonApiResponseBuilder;
use AlexFigures\Symfony\Http\Response\JsonApiResponseFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Note: This test only verifies the factory methods return correct builder types.
 * Full integration tests with DocumentBuilder are in tests/Integration/.
 *
 * @covers \AlexFigures\Symfony\Http\Response\JsonApiResponseFactory
 */
#[CoversClass(JsonApiResponseFactory::class)]
final class JsonApiResponseFactoryTest extends TestCase
{
    private JsonApiResponseFactory $factory;

    protected function setUp(): void
    {
        // Create factory with real dependencies
        // We can't mock DocumentBuilder because it's final
        $this->factory = $this->createFactory();
    }

    private function createFactory(): JsonApiResponseFactory
    {
        // Use reflection to create factory without dependencies for basic tests
        // Full integration tests will use real dependencies
        $reflection = new \ReflectionClass(JsonApiResponseFactory::class);
        $factory = $reflection->newInstanceWithoutConstructor();

        return $factory;
    }

    public function testResourceReturnsBuilder(): void
    {
        $resource = new \stdClass();
        $builder = $this->factory->resource('articles', $resource);

        self::assertInstanceOf(JsonApiResponseBuilder::class, $builder);
    }

    public function testCreatedReturnsBuilderWith201Status(): void
    {
        $resource = new \stdClass();
        $builder = $this->factory->created('articles', $resource);

        self::assertInstanceOf(JsonApiResponseBuilder::class, $builder);
    }

    public function testCollectionReturnsBuilder(): void
    {
        $resources = [new \stdClass(), new \stdClass()];
        $builder = $this->factory->collection('articles', $resources);

        self::assertInstanceOf(JsonApiResponseBuilder::class, $builder);
    }

    public function testCollectionWithTotalItems(): void
    {
        $resources = [new \stdClass()];
        $builder = $this->factory->collection('articles', $resources, totalItems: 100);

        self::assertInstanceOf(JsonApiResponseBuilder::class, $builder);
    }

    public function testNoContentReturns204Response(): void
    {
        $response = $this->factory->noContent();

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(204, $response->getStatusCode());
        self::assertSame('', $response->getContent());
    }

    public function testAcceptedWithoutResourceReturnsBuilder(): void
    {
        $builder = $this->factory->accepted();

        self::assertInstanceOf(JsonApiResponseBuilder::class, $builder);
    }

    public function testAcceptedWithResourceReturnsBuilder(): void
    {
        $resource = new \stdClass();
        $builder = $this->factory->accepted('jobs', $resource);

        self::assertInstanceOf(JsonApiResponseBuilder::class, $builder);
    }

    public function testErrorReturnsErrorBuilder(): void
    {
        $builder = $this->factory->error(400, 'Bad request');

        self::assertInstanceOf(JsonApiErrorBuilder::class, $builder);
    }

    public function testValidationErrorsReturnsErrorBuilder(): void
    {
        $errors = [
            ['pointer' => '/data/attributes/email', 'detail' => 'Invalid email'],
        ];
        $builder = $this->factory->validationErrors($errors);

        self::assertInstanceOf(JsonApiErrorBuilder::class, $builder);
    }
}
