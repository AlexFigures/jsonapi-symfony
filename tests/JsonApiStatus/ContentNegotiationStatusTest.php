<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\JsonApiStatus;

use AlexFigures\Symfony\Bridge\Symfony\EventSubscriber\ContentNegotiationSubscriber;
use AlexFigures\Symfony\Http\Exception\NotAcceptableException;
use AlexFigures\Symfony\Http\Exception\UnsupportedMediaTypeException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ContentNegotiationStatusTest extends TestCase
{
    private ContentNegotiationSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->subscriber = new ContentNegotiationSubscriber(true, 'application/vnd.api+json');
    }

    public function testContentTypeWithUnsupportedParameterTriggers415(): void
    {
        $request = Request::create('/api/articles', 'POST', server: [
            'CONTENT_TYPE' => 'application/vnd.api+json; charset=utf-8',
        ]);

        $event = $this->requestEvent($request);

        try {
            $this->subscriber->onKernelRequest($event);
            self::fail('Expected UnsupportedMediaTypeException (415) for unsupported media type parameter.');
        } catch (UnsupportedMediaTypeException $exception) {
            self::assertSame(415, $exception->getStatusCode());
        }
    }

    public function testContentTypeWithUnsupportedExtensionMustReturn415(): void
    {
        $request = Request::create('/api/articles', 'POST', server: [
            'CONTENT_TYPE' => 'application/vnd.api+json; ext="urn:example:unsupported"',
        ]);

        $event = $this->requestEvent($request);

        try {
            $this->subscriber->onKernelRequest($event);
            self::fail('Spec requires 415 Unsupported Media Type for unsupported ext parameter.');
        } catch (UnsupportedMediaTypeException $exception) {
            self::assertSame(415, $exception->getStatusCode());
        }
    }

    public function testAcceptHeaderWithUnsupportedParameterTriggers406(): void
    {
        $request = Request::create('/api/articles', 'GET', server: [
            'HTTP_ACCEPT' => 'application/vnd.api+json; charset=utf-8',
        ]);

        $event = $this->requestEvent($request);

        try {
            $this->subscriber->onKernelRequest($event);
            self::fail('Expected NotAcceptableException (406) for unsupported Accept parameter.');
        } catch (NotAcceptableException $exception) {
            self::assertSame(406, $exception->getStatusCode());
        }
    }

    public function testAcceptHeaderWithUnsupportedExtensionMustReturn406(): void
    {
        $request = Request::create('/api/articles', 'GET', server: [
            'HTTP_ACCEPT' => 'application/vnd.api+json; ext="urn:example:unsupported"',
        ]);

        $event = $this->requestEvent($request);

        try {
            $this->subscriber->onKernelRequest($event);
            self::fail('Spec requires 406 Not Acceptable for unsupported ext parameter.');
        } catch (NotAcceptableException $exception) {
            self::assertSame(406, $exception->getStatusCode());
        }
    }

    public function testAcceptHeaderWithUnknownProfileIsIgnoredAndVaryAcceptSet(): void
    {
        $request = Request::create('/api/articles', 'GET', server: [
            'HTTP_ACCEPT' => 'application/vnd.api+json; profile="urn:example:unknown"',
        ]);

        $event = $this->requestEvent($request);

        $this->subscriber->onKernelRequest($event);

        $response = new Response();
        $this->subscriber->onKernelResponse($this->responseEvent($request, $response));

        self::assertSame('Accept', $response->headers->get('Vary'));
    }

    private function requestEvent(Request $request): RequestEvent
    {
        return new RequestEvent($this->kernel(), $request, HttpKernelInterface::MAIN_REQUEST);
    }

    private function responseEvent(Request $request, Response $response): ResponseEvent
    {
        return new ResponseEvent($this->kernel(), $request, HttpKernelInterface::MAIN_REQUEST, $response);
    }

    private function kernel(): HttpKernelInterface
    {
        return new class () implements HttpKernelInterface {
            public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
            {
                return new Response();
            }
        };
    }
}
