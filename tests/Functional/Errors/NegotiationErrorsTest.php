<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Functional\Errors;

use AlexFigures\Symfony\Bridge\Symfony\EventSubscriber\ContentNegotiationSubscriber;
use AlexFigures\Symfony\Bridge\Symfony\Negotiation\ChannelScopeMatcher;
use AlexFigures\Symfony\Bridge\Symfony\Negotiation\ConfigMediaTypePolicyProvider;
use AlexFigures\Symfony\Http\Negotiation\MediaType;
use AlexFigures\Symfony\Http\Negotiation\MediaTypePolicyProviderInterface;
use AlexFigures\Symfony\Tests\Functional\JsonApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Throwable;

final class NegotiationErrorsTest extends JsonApiTestCase
{
    public function testUnsupportedMediaType(): void
    {
        $request = Request::create(
            '/api/articles',
            'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{}',
        );

        try {
            ($this->createController())($request, 'articles');
            self::fail('Expected exception to be thrown.');
        } catch (Throwable $exception) {
            $response = $this->handleException($request, $exception);
        }

        $errors = $this->assertErrors($response, 415);
        self::assertSame('unsupported-media-type', $errors[0]['code']);
        $this->assertErrorHeader($errors[0], 'Content-Type');
        self::assertSame('Accept', $response->headers->get('Vary'));
        self::assertSame('00000000-0000-4000-8000-000000000000', $response->headers->get('X-Request-ID'));
        self::assertSame('00000000-0000-4000-8000-000000000000', $errors[0]['id'] ?? null);
    }

    public function testNotAcceptable(): void
    {
        $subscriber = $this->createSubscriber();
        $request = Request::create('/api/articles', 'GET', server: ['HTTP_ACCEPT' => 'application/xml']);
        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        try {
            $subscriber->onKernelRequest($event);
            self::fail('Expected exception to be thrown.');
        } catch (Throwable $exception) {
            $response = $this->handleException($request, $exception);
        }

        $errors = $this->assertErrors($response, 406);
        self::assertSame('not-acceptable', $errors[0]['code']);
        $this->assertErrorHeader($errors[0], 'Accept');
    }

    private function createSubscriber(): ContentNegotiationSubscriber
    {
        return new ContentNegotiationSubscriber(true, $this->createPolicyProvider());
    }

    private function createPolicyProvider(): MediaTypePolicyProviderInterface
    {
        return new ConfigMediaTypePolicyProvider(
            [
                'default' => [
                    'request' => ['allowed' => [MediaType::JSON_API]],
                    'response' => [
                        'default' => MediaType::JSON_API,
                        'negotiable' => [],
                    ],
                ],
                'channels' => [],
            ],
            new ChannelScopeMatcher()
        );
    }
}
