<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Functional;

use AlexFigures\Symfony\Bridge\Symfony\EventSubscriber\ContentNegotiationSubscriber;
use AlexFigures\Symfony\Bridge\Symfony\Negotiation\ChannelScopeMatcher;
use AlexFigures\Symfony\Bridge\Symfony\Negotiation\ConfigMediaTypePolicyProvider;
use AlexFigures\Symfony\Http\Exception\JsonApiHttpException;
use AlexFigures\Symfony\Http\Exception\NotAcceptableException;
use AlexFigures\Symfony\Http\Exception\UnsupportedMediaTypeException;
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
        $this->subscriber = $this->createSubscriber();
    }

    public function testUnsupportedMediaTypeTriggers415(): void
    {
        $request = Request::create('/articles', 'POST', server: ['CONTENT_TYPE' => 'application/json']);
        $event = new RequestEvent($this->createKernel(), $request, HttpKernelInterface::MAIN_REQUEST);

        $this->expectException(JsonApiHttpException::class);
        $this->expectExceptionCode(0);
        $this->expectExceptionMessage('The "application/json" media type is not allowed for this endpoint.');

        $this->subscriber->onKernelRequest($event);
    }

    public function testNotAcceptableTriggers406(): void
    {
        $request = Request::create('/articles', 'GET', server: ['HTTP_ACCEPT' => 'application/json']);
        $event = new RequestEvent($this->createKernel(), $request, HttpKernelInterface::MAIN_REQUEST);

        $this->expectException(JsonApiHttpException::class);
        $this->expectExceptionMessage('Requested representation is not available. Allowed types: application/vnd.api+json.');

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

    public function testContentTypeWithExtParameterIsRejected(): void
    {
        $request = Request::create('/articles', 'POST', server: [
            'CONTENT_TYPE' => 'application/vnd.api+json; ext="https://jsonapi.org/ext/atomic"',
            'HTTP_ACCEPT' => 'application/vnd.api+json',
        ]);
        $event = new RequestEvent($this->createKernel(), $request, HttpKernelInterface::MAIN_REQUEST);

        // Extensions are not supported, should throw exception
        $this->expectException(UnsupportedMediaTypeException::class);
        $this->expectExceptionMessage('JSON:API media type contains unsupported extension URI in "ext" parameter.');

        $this->subscriber->onKernelRequest($event);
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

    public function testAcceptWithExtParameterIsRejected(): void
    {
        $request = Request::create('/articles', 'GET', server: [
            'HTTP_ACCEPT' => 'application/vnd.api+json; ext="https://jsonapi.org/ext/atomic"',
        ]);
        $event = new RequestEvent($this->createKernel(), $request, HttpKernelInterface::MAIN_REQUEST);

        // Extensions are not supported, should throw exception
        $this->expectException(NotAcceptableException::class);
        $this->expectExceptionMessage('JSON:API media type in Accept header contains unsupported extension URI in "ext" parameter.');

        $this->subscriber->onKernelRequest($event);
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

    public function testWildcardAcceptHeaderIsAllowed(): void
    {
        $request = Request::create('/articles', 'GET', server: ['HTTP_ACCEPT' => 'application/*']);
        $event = new RequestEvent($this->createKernel(), $request, HttpKernelInterface::MAIN_REQUEST);

        $this->subscriber->onKernelRequest($event);

        self::assertTrue(true);
    }

    public function testSandboxChannelAllowsMultipart(): void
    {
        $subscriber = $this->createSubscriber([
            'channels' => [
                'sandbox' => [
                    'scope' => ['path_prefix' => '^/sandbox'],
                    'request' => ['allowed' => ['multipart/form-data']],
                    'response' => ['default' => 'application/json'],
                ],
            ],
        ]);

        $request = Request::create('/sandbox/upload', 'POST', server: [
            'CONTENT_TYPE' => 'multipart/form-data; boundary=abc',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $event = new RequestEvent($this->createKernel(), $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertTrue(true);
    }

    public function testDocsChannelSkipsStrictNegotiation(): void
    {
        $subscriber = $this->createSubscriber([
            'channels' => [
                'docs' => [
                    'scope' => ['path_prefix' => '^/_jsonapi/docs'],
                    'request' => ['allowed' => ['*']],
                    'response' => [
                        'default' => 'text/html',
                        'negotiable' => ['text/html'],
                    ],
                ],
            ],
        ]);

        $request = Request::create('/_jsonapi/docs/index.html', 'GET', server: [
            'HTTP_ACCEPT' => 'text/html',
        ]);
        $event = new RequestEvent($this->createKernel(), $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        $response = new Response();
        $subscriber->onKernelResponse(new ResponseEvent(
            $this->createKernel(),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        ));

        self::assertSame('text/html', $response->headers->get('Content-Type'));
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

    /**
     * @param array{default?: array{request?: array{allowed?: list<string>}, response?: array{default?: string, negotiable?: list<string>}}, channels?: array<string, array{scope?: array<string, string>, request?: array{allowed?: list<string>}, response?: array{default?: string, negotiable?: list<string>}}>} $overrides
     */
    private function createSubscriber(array $overrides = []): ContentNegotiationSubscriber
    {
        $base = [
            'default' => [
                'request' => ['allowed' => [MediaType::JSON_API]],
                'response' => [
                    'default' => MediaType::JSON_API,
                    'negotiable' => [],
                ],
            ],
            'channels' => [],
        ];

        $config = array_replace_recursive($base, $overrides);

        $provider = new ConfigMediaTypePolicyProvider($config, new ChannelScopeMatcher());

        return new ContentNegotiationSubscriber(true, $provider);
    }
}
