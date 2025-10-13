<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\CustomRoute\Result;

use AlexFigures\Symfony\CustomRoute\Result\CustomRouteResult;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * @covers \AlexFigures\Symfony\CustomRoute\Result\CustomRouteResult
 */
final class CustomRouteResultTest extends TestCase
{
    public function testResourceFactoryMethod(): void
    {
        $resource = new \stdClass();
        $result = CustomRouteResult::resource($resource);

        self::assertTrue($result->isResource());
        self::assertFalse($result->isCollection());
        self::assertFalse($result->isError());
        self::assertFalse($result->isNoContent());
        self::assertSame($resource, $result->getData());
        self::assertSame(Response::HTTP_OK, $result->getStatus());
        self::assertSame([], $result->getMeta());
        self::assertSame([], $result->getLinks());
        self::assertSame([], $result->getHeaders());
        self::assertNull($result->getTotalItems());
    }

    public function testResourceWithCustomStatus(): void
    {
        $resource = new \stdClass();
        $result = CustomRouteResult::resource($resource, Response::HTTP_PARTIAL_CONTENT);

        self::assertSame(Response::HTTP_PARTIAL_CONTENT, $result->getStatus());
    }

    public function testCollectionFactoryMethod(): void
    {
        $resources = [new \stdClass(), new \stdClass()];
        $result = CustomRouteResult::collection($resources, 10);

        self::assertTrue($result->isCollection());
        self::assertFalse($result->isResource());
        self::assertSame($resources, $result->getData());
        self::assertSame(Response::HTTP_OK, $result->getStatus());
        self::assertSame(10, $result->getTotalItems());
    }

    public function testCollectionWithoutTotalItems(): void
    {
        $resources = [new \stdClass(), new \stdClass()];
        $result = CustomRouteResult::collection($resources);

        self::assertSame(2, $result->getTotalItems());
    }

    public function testCreatedFactoryMethod(): void
    {
        $resource = new \stdClass();
        $result = CustomRouteResult::created($resource);

        self::assertTrue($result->isResource());
        self::assertSame(Response::HTTP_CREATED, $result->getStatus());
        self::assertSame($resource, $result->getData());
    }

    public function testAcceptedWithResource(): void
    {
        $resource = new \stdClass();
        $result = CustomRouteResult::accepted($resource);

        self::assertTrue($result->isResource());
        self::assertSame(Response::HTTP_ACCEPTED, $result->getStatus());
        self::assertSame($resource, $result->getData());
    }

    public function testAcceptedWithoutResource(): void
    {
        $result = CustomRouteResult::accepted();

        self::assertTrue($result->isNoContent());
        self::assertSame(Response::HTTP_ACCEPTED, $result->getStatus());
        self::assertNull($result->getData());
    }

    public function testNoContentFactoryMethod(): void
    {
        $result = CustomRouteResult::noContent();

        self::assertTrue($result->isNoContent());
        self::assertFalse($result->isResource());
        self::assertFalse($result->isCollection());
        self::assertFalse($result->isError());
        self::assertSame(Response::HTTP_NO_CONTENT, $result->getStatus());
        self::assertNull($result->getData());
    }

    public function testBadRequestFactoryMethod(): void
    {
        $result = CustomRouteResult::badRequest('Invalid input');

        self::assertTrue($result->isError());
        self::assertSame(Response::HTTP_BAD_REQUEST, $result->getStatus());
        self::assertSame(['detail' => 'Invalid input', 'status' => '400'], $result->getData());
    }

    public function testForbiddenFactoryMethod(): void
    {
        $result = CustomRouteResult::forbidden('Access denied');

        self::assertTrue($result->isError());
        self::assertSame(Response::HTTP_FORBIDDEN, $result->getStatus());
        self::assertSame(['detail' => 'Access denied', 'status' => '403'], $result->getData());
    }

    public function testForbiddenWithDefaultMessage(): void
    {
        $result = CustomRouteResult::forbidden();

        self::assertSame(['detail' => 'Access forbidden', 'status' => '403'], $result->getData());
    }

    public function testNotFoundFactoryMethod(): void
    {
        $result = CustomRouteResult::notFound('Resource not found');

        self::assertTrue($result->isError());
        self::assertSame(Response::HTTP_NOT_FOUND, $result->getStatus());
        self::assertSame(['detail' => 'Resource not found', 'status' => '404'], $result->getData());
    }

    public function testNotFoundWithDefaultMessage(): void
    {
        $result = CustomRouteResult::notFound();

        self::assertSame(['detail' => 'Resource not found', 'status' => '404'], $result->getData());
    }

    public function testConflictFactoryMethod(): void
    {
        $result = CustomRouteResult::conflict('Already exists');

        self::assertTrue($result->isError());
        self::assertSame(Response::HTTP_CONFLICT, $result->getStatus());
        self::assertSame(['detail' => 'Already exists', 'status' => '409'], $result->getData());
    }

    public function testUnprocessableFactoryMethod(): void
    {
        $errors = [
            ['pointer' => '/data/attributes/email', 'detail' => 'Invalid email'],
            ['pointer' => '/data/attributes/age', 'detail' => 'Must be at least 18'],
        ];
        $result = CustomRouteResult::unprocessable($errors);

        self::assertTrue($result->isError());
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $result->getStatus());
        self::assertSame($errors, $result->getData());
    }

    public function testWithMeta(): void
    {
        $result = CustomRouteResult::resource(new \stdClass())
            ->withMeta(['key1' => 'value1'])
            ->withMeta(['key2' => 'value2']);

        self::assertSame(['key1' => 'value1', 'key2' => 'value2'], $result->getMeta());
    }

    public function testWithLinks(): void
    {
        $result = CustomRouteResult::resource(new \stdClass())
            ->withLinks(['self' => '/api/resource/1'])
            ->withLinks(['related' => '/api/related']);

        self::assertSame(['self' => '/api/resource/1', 'related' => '/api/related'], $result->getLinks());
    }

    public function testWithStatus(): void
    {
        $result = CustomRouteResult::resource(new \stdClass())
            ->withStatus(Response::HTTP_PARTIAL_CONTENT);

        self::assertSame(Response::HTTP_PARTIAL_CONTENT, $result->getStatus());
    }

    public function testWithHeader(): void
    {
        $result = CustomRouteResult::resource(new \stdClass())
            ->withHeader('X-Custom-Header', 'value1')
            ->withHeader('X-Another-Header', 'value2');

        self::assertSame([
            'X-Custom-Header' => 'value1',
            'X-Another-Header' => 'value2',
        ], $result->getHeaders());
    }

    public function testFluentApiChaining(): void
    {
        $resource = new \stdClass();
        $result = CustomRouteResult::resource($resource)
            ->withMeta(['version' => 2])
            ->withLinks(['self' => '/api/resource/1'])
            ->withStatus(Response::HTTP_PARTIAL_CONTENT)
            ->withHeader('X-Custom', 'value');

        self::assertSame($resource, $result->getData());
        self::assertSame(['version' => 2], $result->getMeta());
        self::assertSame(['self' => '/api/resource/1'], $result->getLinks());
        self::assertSame(Response::HTTP_PARTIAL_CONTENT, $result->getStatus());
        self::assertSame(['X-Custom' => 'value'], $result->getHeaders());
    }

    public function testImmutability(): void
    {
        $original = CustomRouteResult::resource(new \stdClass());
        $modified = $original->withMeta(['key' => 'value']);

        self::assertNotSame($original, $modified);
        self::assertSame([], $original->getMeta());
        self::assertSame(['key' => 'value'], $modified->getMeta());
    }

    public function testTypeChecks(): void
    {
        $resourceResult = CustomRouteResult::resource(new \stdClass());
        self::assertTrue($resourceResult->isResource());
        self::assertFalse($resourceResult->isCollection());
        self::assertFalse($resourceResult->isError());
        self::assertFalse($resourceResult->isNoContent());

        $collectionResult = CustomRouteResult::collection([]);
        self::assertTrue($collectionResult->isCollection());
        self::assertFalse($collectionResult->isResource());

        $errorResult = CustomRouteResult::badRequest('error');
        self::assertTrue($errorResult->isError());
        self::assertFalse($errorResult->isResource());

        $noContentResult = CustomRouteResult::noContent();
        self::assertTrue($noContentResult->isNoContent());
        self::assertFalse($noContentResult->isResource());
    }
}
