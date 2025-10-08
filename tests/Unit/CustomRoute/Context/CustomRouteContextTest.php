<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Unit\CustomRoute\Context;

use JsonApi\Symfony\CustomRoute\Context\CustomRouteContext;
use JsonApi\Symfony\Query\Criteria;
use JsonApi\Symfony\Query\Pagination;
use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @covers \JsonApi\Symfony\CustomRoute\Context\CustomRouteContext
 */
final class CustomRouteContextTest extends TestCase
{
    public function testGetResourceReturnsPreloadedResource(): void
    {
        $resource = new \stdClass();
        $context = $this->createContext(resource: $resource);

        self::assertSame($resource, $context->getResource());
    }

    public function testGetResourceThrowsWhenNoResourceLoaded(): void
    {
        $context = $this->createContext(resource: null);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No resource available in this context');
        $context->getResource();
    }

    public function testHasResourceReturnsTrueWhenResourceLoaded(): void
    {
        $resource = new \stdClass();
        $context = $this->createContext(resource: $resource);

        self::assertTrue($context->hasResource());
    }

    public function testHasResourceReturnsFalseWhenNoResourceLoaded(): void
    {
        $context = $this->createContext(resource: null);

        self::assertFalse($context->hasResource());
    }

    public function testGetResourceTypeReturnsType(): void
    {
        $context = $this->createContext(resourceType: 'articles');

        self::assertSame('articles', $context->getResourceType());
    }

    public function testGetParamReturnsRouteParameter(): void
    {
        $context = $this->createContext(routeParams: ['id' => '123', 'action' => 'publish']);

        self::assertSame('123', $context->getParam('id'));
        self::assertSame('publish', $context->getParam('action'));
    }

    public function testGetParamThrowsForNonExistentParameter(): void
    {
        $context = $this->createContext(routeParams: ['id' => '123']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Route parameter "missing" does not exist');
        $context->getParam('missing');
    }

    public function testGetParamsReturnsAllRouteParameters(): void
    {
        $params = ['id' => '123', 'action' => 'publish'];
        $context = $this->createContext(routeParams: $params);

        self::assertSame($params, $context->getParams());
    }

    public function testGetRequestReturnsUnderlyingRequest(): void
    {
        $request = Request::create('/test');
        $context = $this->createContext(request: $request);

        self::assertSame($request, $context->getRequest());
    }

    public function testGetCriteriaReturnsParsedCriteria(): void
    {
        $criteria = new Criteria(new Pagination(1, 10));
        $criteria->include = ['author', 'comments'];
        $criteria->fields = ['articles' => ['title', 'body']];
        $criteria->sort = [new \JsonApi\Symfony\Query\Sorting('createdAt', false)];

        $context = $this->createContext(criteria: $criteria);

        self::assertSame($criteria, $context->getCriteria());
    }

    public function testGetBodyReturnsDecodedRequestBody(): void
    {
        $body = ['data' => ['type' => 'articles', 'attributes' => ['title' => 'Test']]];
        $context = $this->createContext(body: $body);

        self::assertSame($body, $context->getBody());
    }

    public function testGetBodyReturnsEmptyArrayWhenNoBody(): void
    {
        $context = $this->createContext(body: []);

        self::assertSame([], $context->getBody());
    }

    public function testGetQueryParamReturnsQueryParameter(): void
    {
        $request = Request::create('/test?q=search&limit=20');
        $context = $this->createContext(request: $request);

        self::assertSame('search', $context->getQueryParam('q'));
        self::assertSame('20', $context->getQueryParam('limit'));
    }

    public function testGetQueryParamReturnsDefaultWhenNotSet(): void
    {
        $request = Request::create('/test');
        $context = $this->createContext(request: $request);

        self::assertSame('default', $context->getQueryParam('missing', 'default'));
        self::assertNull($context->getQueryParam('missing'));
    }

    public function testHasQueryParamReturnsTrueWhenParameterExists(): void
    {
        $request = Request::create('/test?q=search');
        $context = $this->createContext(request: $request);

        self::assertTrue($context->hasQueryParam('q'));
    }

    public function testHasQueryParamReturnsFalseWhenParameterDoesNotExist(): void
    {
        $request = Request::create('/test');
        $context = $this->createContext(request: $request);

        self::assertFalse($context->hasQueryParam('missing'));
    }

    public function testContextIsImmutable(): void
    {
        $resource = new \stdClass();
        $request = Request::create('/test');
        $criteria = new Criteria(new Pagination(1, 10));
        $routeParams = ['id' => '123'];
        $body = ['key' => 'value'];

        $context = new CustomRouteContext(
            request: $request,
            resource: $resource,
            resourceType: 'articles',
            routeParams: $routeParams,
            criteria: $criteria,
            body: $body
        );

        // Verify all properties are accessible
        self::assertSame($resource, $context->getResource());
        self::assertSame($request, $context->getRequest());
        self::assertSame('articles', $context->getResourceType());
        self::assertSame($routeParams, $context->getParams());
        self::assertSame($criteria, $context->getCriteria());
        self::assertSame($body, $context->getBody());
    }

    /**
     * Helper to create a context with default values.
     */
    private function createContext(
        ?Request $request = null,
        ?object $resource = null,
        string $resourceType = 'articles',
        array $routeParams = [],
        ?Criteria $criteria = null,
        array $body = []
    ): CustomRouteContext {
        return new CustomRouteContext(
            request: $request ?? Request::create('/test'),
            resource: $resource,
            resourceType: $resourceType,
            routeParams: $routeParams,
            criteria: $criteria ?? new Criteria(new Pagination(1, 10)),
            body: $body
        );
    }
}

