<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Bridge\Symfony\EventSubscriber;

use AlexFigures\Symfony\Bridge\Symfony\EventSubscriber\ContentNegotiationSubscriber;
use AlexFigures\Symfony\Http\Exception\NotAcceptableException;
use AlexFigures\Symfony\Http\Exception\UnsupportedMediaTypeException;
use AlexFigures\Symfony\Http\Negotiation\MediaType;
use AlexFigures\Symfony\Http\Negotiation\MediaTypePolicy;
use AlexFigures\Symfony\Http\Negotiation\MediaTypePolicyProviderInterface;
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
        $subscriber = $this->createSubscriber($this->jsonApiPolicy());
        $request = Request::create('/api/articles', 'GET');
        $kernel = $this->createMock(HttpKernelInterface::class);

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST);
        $subscriber->onKernelRequest($event);

        $this->addToAssertionCount(1);
    }

    public function testSkipsWhenStrictModeDisabled(): void
    {
        $subscriber = $this->createSubscriber($this->jsonApiPolicy(), false);
        $request = Request::create('/api/articles', 'GET');
        $request->headers->set('Accept', 'text/html');
        $kernel = $this->createMock(HttpKernelInterface::class);

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($event);

        $this->addToAssertionCount(1);
    }

    public function testThrowsNotAcceptableWhenAcceptHeaderMissingAllowedType(): void
    {
        $subscriber = $this->createSubscriber($this->jsonApiPolicy());
        $request = Request::create('/api/articles', 'GET');
        $request->headers->set('Accept', 'text/html');
        $kernel = $this->createMock(HttpKernelInterface::class);

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->expectException(NotAcceptableException::class);
        $subscriber->onKernelRequest($event);
    }

    public function testAcceptsCorrectMediaType(): void
    {
        $subscriber = $this->createSubscriber($this->jsonApiPolicy());
        $request = Request::create('/api/articles', 'GET');
        $request->headers->set('Accept', MediaType::JSON_API);
        $kernel = $this->createMock(HttpKernelInterface::class);

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($event);

        $this->addToAssertionCount(1);
    }

    public function testThrowsUnsupportedMediaTypeForWrongContentType(): void
    {
        $subscriber = $this->createSubscriber($this->jsonApiPolicy());
        $request = Request::create('/api/articles', 'POST', [], [], [], [], '{}');
        $request->headers->set('Content-Type', 'application/json');
        $request->headers->set('Accept', MediaType::JSON_API);
        $kernel = $this->createMock(HttpKernelInterface::class);

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->expectException(UnsupportedMediaTypeException::class);
        $subscriber->onKernelRequest($event);
    }

    public function testAllowsAnyRequestPolicySkipsContentTypeValidation(): void
    {
        $policy = new MediaTypePolicy(['*'], [MediaType::JSON_API], MediaType::JSON_API, false);
        $subscriber = $this->createSubscriber($policy);
        $request = Request::create('/sandbox', 'POST');
        $request->headers->set('Content-Type', 'multipart/form-data; boundary=abc');
        $request->headers->set('Accept', MediaType::JSON_API);
        $kernel = $this->createMock(HttpKernelInterface::class);

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($event);

        $this->addToAssertionCount(1);
    }

    public function testAllowsAnyResponsePolicySkipsAcceptValidation(): void
    {
        $policy = new MediaTypePolicy([MediaType::JSON_API], ['*'], MediaType::JSON_API, true);
        $subscriber = $this->createSubscriber($policy);
        $request = Request::create('/sandbox', 'GET');
        $request->headers->set('Accept', 'text/html');
        $kernel = $this->createMock(HttpKernelInterface::class);

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($event);

        $this->addToAssertionCount(1);
    }

    public function testAddsVaryHeaderAndDefaultContentTypeToResponse(): void
    {
        $subscriber = $this->createSubscriber($this->jsonApiPolicy());
        $request = Request::create('/api/articles', 'GET');
        $kernel = $this->createMock(HttpKernelInterface::class);

        $response = new Response();
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);

        self::assertSame('Accept', $response->headers->get('Vary'));
        self::assertSame(MediaType::JSON_API, $response->headers->get('Content-Type'));
    }

    private function createSubscriber(MediaTypePolicy $policy, bool $strict = true): ContentNegotiationSubscriber
    {
        $provider = new class ($policy) implements MediaTypePolicyProviderInterface {
            public function __construct(private MediaTypePolicy $policy)
            {
            }

            public function getPolicy(Request $request): MediaTypePolicy
            {
                return $this->policy;
            }
        };

        return new ContentNegotiationSubscriber($strict, $provider);
    }

    private function jsonApiPolicy(): MediaTypePolicy
    {
        return new MediaTypePolicy(
            [MediaType::JSON_API],
            [MediaType::JSON_API],
            MediaType::JSON_API,
            true
        );
    }
}
