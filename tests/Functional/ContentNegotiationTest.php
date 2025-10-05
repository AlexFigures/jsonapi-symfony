<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Functional;

use JsonApi\Symfony\Bridge\Symfony\EventSubscriber\ContentNegotiationSubscriber;
use JsonApi\Symfony\Http\Exception\JsonApiHttpException;
use JsonApi\Symfony\Http\Negotiation\MediaType;
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
