<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Functional;

use AlexFigures\Symfony\Bridge\Symfony\EventSubscriber\ContentNegotiationSubscriber;
use AlexFigures\Symfony\Http\Exception\JsonApiHttpException;
use AlexFigures\Symfony\Http\Negotiation\MediaType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(ContentNegotiationSubscriber::class)]
final class ContentNegotiationTest extends TestCase
{
    private ContentNegotiationSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->subscriber = new ContentNegotiationSubscriber(true, MediaType::JSON_API);
    }

    public function testUnsupportedMediaTypeTriggers415(): void
    {
        $request = Request::create('/articles', 'POST', server: ['CONTENT_TYPE' => 'application/json']);
        $event = new RequestEvent($this->createKernel(), $request, HttpKernelInterface::MAIN_REQUEST);

        $this->expectException(JsonApiHttpException::class);
        $this->expectExceptionCode(0);
        $this->expectExceptionMessage('JSON:API requires the "application/vnd.api+json" media type.');

        $this->subscriber->onKernelRequest($event);
    }

    public function testNotAcceptableTriggers406(): void
    {
        $request = Request::create('/articles', 'GET', server: ['HTTP_ACCEPT' => 'application/json']);
        $event = new RequestEvent($this->createKernel(), $request, HttpKernelInterface::MAIN_REQUEST);

        $this->expectException(JsonApiHttpException::class);
        $this->expectExceptionMessage('Requested representation is not available in application/vnd.api+json.');

        $this->subscriber->onKernelRequest($event);
    }

    public function testResponseContainsVaryAccept(): void
    {
        $response = new Response();
        $event = new ResponseEvent(
            $this->createKernel(),
            Request::create('/articles', 'GET'),
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        $this->subscriber->onKernelResponse($event);

        self::assertSame('Accept', $response->headers->get('Vary'));
    }

    public function testContentTypeWithCharsetParameterTriggers415(): void
    {
        $request = Request::create('/articles', 'POST', server: [
            'CONTENT_TYPE' => 'application/vnd.api+json; charset=utf-8',
        ]);
        $event = new RequestEvent($this->createKernel(), $request, HttpKernelInterface::MAIN_REQUEST);

        $this->expectException(JsonApiHttpException::class);
        $this->expectExceptionMessage('JSON:API media type must not have parameters other than "ext" or "profile".');

        $this->subscriber->onKernelRequest($event);
    }

    public function testContentTypeWithVersionParameterTriggers415(): void
    {
        $request = Request::create('/articles', 'POST', server: [
            'CONTENT_TYPE' => 'application/vnd.api+json; version=1.0',
        ]);
        $event = new RequestEvent($this->createKernel(), $request, HttpKernelInterface::MAIN_REQUEST);

        $this->expectException(JsonApiHttpException::class);
        $this->expectExceptionMessage('JSON:API media type must not have parameters other than "ext" or "profile".');

        $this->subscriber->onKernelRequest($event);
    }

    public function testContentTypeWithExtParameterIsAllowed(): void
    {
        $request = Request::create('/articles', 'POST', server: [
            'CONTENT_TYPE' => 'application/vnd.api+json; ext="https://jsonapi.org/ext/atomic"',
            'HTTP_ACCEPT' => 'application/vnd.api+json',
        ]);
        $event = new RequestEvent($this->createKernel(), $request, HttpKernelInterface::MAIN_REQUEST);

        // Should not throw exception
        $this->subscriber->onKernelRequest($event);

        self::assertTrue(true); // Assert that no exception was thrown
    }

    public function testContentTypeWithProfileParameterIsAllowed(): void
    {
        $request = Request::create('/articles', 'POST', server: [
            'CONTENT_TYPE' => 'application/vnd.api+json; profile="https://example.com/profile"',
            'HTTP_ACCEPT' => 'application/vnd.api+json',
        ]);
        $event = new RequestEvent($this->createKernel(), $request, HttpKernelInterface::MAIN_REQUEST);

        // Should not throw exception
        $this->subscriber->onKernelRequest($event);

        self::assertTrue(true); // Assert that no exception was thrown
    }

    public function testAcceptWithCharsetParameterTriggers406(): void
    {
        $request = Request::create('/articles', 'GET', server: [
            'HTTP_ACCEPT' => 'application/vnd.api+json; charset=utf-8',
        ]);
        $event = new RequestEvent($this->createKernel(), $request, HttpKernelInterface::MAIN_REQUEST);

        $this->expectException(JsonApiHttpException::class);
        $this->expectExceptionMessage('JSON:API media type in Accept header must not have parameters other than "ext" or "profile".');

        $this->subscriber->onKernelRequest($event);
    }

    public function testAcceptWithVersionParameterTriggers406(): void
    {
        $request = Request::create('/articles', 'GET', server: [
            'HTTP_ACCEPT' => 'application/vnd.api+json; version=1.0',
        ]);
        $event = new RequestEvent($this->createKernel(), $request, HttpKernelInterface::MAIN_REQUEST);

        $this->expectException(JsonApiHttpException::class);
        $this->expectExceptionMessage('JSON:API media type in Accept header must not have parameters other than "ext" or "profile".');

        $this->subscriber->onKernelRequest($event);
    }

    public function testAcceptWithExtParameterIsAllowed(): void
    {
        $request = Request::create('/articles', 'GET', server: [
            'HTTP_ACCEPT' => 'application/vnd.api+json; ext="https://jsonapi.org/ext/atomic"',
        ]);
        $event = new RequestEvent($this->createKernel(), $request, HttpKernelInterface::MAIN_REQUEST);

        // Should not throw exception
        $this->subscriber->onKernelRequest($event);

        self::assertTrue(true); // Assert that no exception was thrown
    }

    public function testAcceptWithProfileParameterIsAllowed(): void
    {
        $request = Request::create('/articles', 'GET', server: [
            'HTTP_ACCEPT' => 'application/vnd.api+json; profile="https://example.com/profile"',
        ]);
        $event = new RequestEvent($this->createKernel(), $request, HttpKernelInterface::MAIN_REQUEST);

        // Should not throw exception
        $this->subscriber->onKernelRequest($event);

        self::assertTrue(true); // Assert that no exception was thrown
    }

    public function testAcceptWithMultipleUnsupportedParametersTriggers406(): void
    {
        $request = Request::create('/articles', 'GET', server: [
            'HTTP_ACCEPT' => 'application/vnd.api+json; charset=utf-8; version=1.0',
        ]);
        $event = new RequestEvent($this->createKernel(), $request, HttpKernelInterface::MAIN_REQUEST);

        $this->expectException(JsonApiHttpException::class);
        $this->expectExceptionMessage('JSON:API media type in Accept header must not have parameters other than "ext" or "profile".');

        $this->subscriber->onKernelRequest($event);
    }

    private function createKernel(): HttpKernelInterface
    {
        return new class () implements HttpKernelInterface {
            public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
            {
                return new Response();
            }
        };
    }
}
