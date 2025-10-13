<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Bridge\Symfony\EventSubscriber;

use AlexFigures\Symfony\Bridge\Symfony\EventSubscriber\ContentNegotiationSubscriber;
use AlexFigures\Symfony\Http\Exception\NotAcceptableException;
use AlexFigures\Symfony\Http\Exception\UnsupportedMediaTypeException;
use AlexFigures\Symfony\Http\Negotiation\MediaType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class ContentNegotiationSubscriberTest extends TestCase
{
    public function testSubscribesToCorrectEvents(): void
    {
        $events = ContentNegotiationSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(KernelEvents::REQUEST, $events);
        self::assertArrayHasKey(KernelEvents::RESPONSE, $events);
        self::assertSame(['onKernelRequest', 512], $events[KernelEvents::REQUEST]);
        self::assertSame(['onKernelResponse', -512], $events[KernelEvents::RESPONSE]);
    }

    public function testSkipsSubRequests(): void
    {
        $subscriber = new ContentNegotiationSubscriber(true, MediaType::JSON_API);
        $request = Request::create('/api/articles', 'GET');
        $kernel = $this->createMock(HttpKernelInterface::class);

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST);
        $subscriber->onKernelRequest($event);

        // No exception should be thrown
        $this->addToAssertionCount(1);
    }

    public function testSkipsWhenStrictModeDisabled(): void
    {
        $subscriber = new ContentNegotiationSubscriber(false, MediaType::JSON_API);
        $request = Request::create('/api/articles', 'GET');
        $request->headers->set('Accept', 'text/html');
        $kernel = $this->createMock(HttpKernelInterface::class);

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($event);

        // No exception should be thrown even with wrong Accept header
        $this->addToAssertionCount(1);
    }

    public function testThrowsNotAcceptableWhenAcceptHeaderMissing(): void
    {
        $subscriber = new ContentNegotiationSubscriber(true, MediaType::JSON_API);
        $request = Request::create('/api/articles', 'GET');
        $request->headers->set('Accept', 'text/html');
        $kernel = $this->createMock(HttpKernelInterface::class);

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->expectException(NotAcceptableException::class);
        $subscriber->onKernelRequest($event);
    }

    public function testAcceptsCorrectMediaType(): void
    {
        $subscriber = new ContentNegotiationSubscriber(true, MediaType::JSON_API);
        $request = Request::create('/api/articles', 'GET');
        $request->headers->set('Accept', MediaType::JSON_API);
        $kernel = $this->createMock(HttpKernelInterface::class);

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($event);

        // No exception should be thrown
        $this->addToAssertionCount(1);
    }

    public function testThrowsUnsupportedMediaTypeForWrongContentType(): void
    {
        $subscriber = new ContentNegotiationSubscriber(true, MediaType::JSON_API);
        $request = Request::create('/api/articles', 'POST', [], [], [], [], '{}');
        $request->headers->set('Content-Type', 'application/json');
        $request->headers->set('Accept', MediaType::JSON_API);
        $kernel = $this->createMock(HttpKernelInterface::class);

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->expectException(UnsupportedMediaTypeException::class);
        $subscriber->onKernelRequest($event);
    }

    public function testSkipsDocumentationRoutesByRouteName(): void
    {
        $subscriber = new ContentNegotiationSubscriber(true, MediaType::JSON_API);
        $request = Request::create('/_jsonapi/docs', 'GET');
        $request->headers->set('Accept', 'text/html');
        $request->attributes->set('_route', 'jsonapi.docs.ui');
        $kernel = $this->createMock(HttpKernelInterface::class);

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($event);

        // No exception should be thrown for documentation routes
        $this->addToAssertionCount(1);
    }

    public function testSkipsOpenApiRouteByRouteName(): void
    {
        $subscriber = new ContentNegotiationSubscriber(true, MediaType::JSON_API);
        $request = Request::create('/_jsonapi/openapi.json', 'GET');
        $request->headers->set('Accept', 'application/json');
        $request->attributes->set('_route', 'jsonapi.docs.openapi');
        $kernel = $this->createMock(HttpKernelInterface::class);

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($event);

        // No exception should be thrown for OpenAPI spec route
        $this->addToAssertionCount(1);
    }

    public function testSkipsDocumentationRoutesByPath(): void
    {
        $subscriber = new ContentNegotiationSubscriber(true, MediaType::JSON_API);
        $request = Request::create('/_jsonapi/docs', 'GET');
        $request->headers->set('Accept', 'text/html');
        $kernel = $this->createMock(HttpKernelInterface::class);

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($event);

        // No exception should be thrown even without route name
        $this->addToAssertionCount(1);
    }

    public function testSkipsOpenApiRouteByPath(): void
    {
        $subscriber = new ContentNegotiationSubscriber(true, MediaType::JSON_API);
        $request = Request::create('/_jsonapi/openapi.json', 'GET');
        $request->headers->set('Accept', 'application/json');
        $kernel = $this->createMock(HttpKernelInterface::class);

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($event);

        // No exception should be thrown even without route name
        $this->addToAssertionCount(1);
    }

    public function testAddsVaryHeaderToResponse(): void
    {
        $subscriber = new ContentNegotiationSubscriber(true, MediaType::JSON_API);
        $request = Request::create('/api/articles', 'GET');
        $response = new Response();
        $kernel = $this->createMock(HttpKernelInterface::class);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $subscriber->onKernelResponse($event);

        self::assertSame('Accept', $response->headers->get('Vary'));
    }

    public function testMergesVaryHeaderWithExisting(): void
    {
        $subscriber = new ContentNegotiationSubscriber(true, MediaType::JSON_API);
        $request = Request::create('/api/articles', 'GET');
        $response = new Response();
        $response->headers->set('Vary', 'Authorization');
        $kernel = $this->createMock(HttpKernelInterface::class);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $subscriber->onKernelResponse($event);

        // The Vary header should contain both values
        $varyHeader = $response->headers->get('Vary');
        self::assertNotNull($varyHeader);
        self::assertStringContainsString('Authorization', $varyHeader);
        self::assertStringContainsString('Accept', $varyHeader);
    }

    public function testDoesNotDuplicateVaryHeader(): void
    {
        $subscriber = new ContentNegotiationSubscriber(true, MediaType::JSON_API);
        $request = Request::create('/api/articles', 'GET');
        $response = new Response();
        $response->headers->set('Vary', 'Accept');
        $kernel = $this->createMock(HttpKernelInterface::class);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $subscriber->onKernelResponse($event);

        self::assertSame('Accept', $response->headers->get('Vary'));
    }
}
