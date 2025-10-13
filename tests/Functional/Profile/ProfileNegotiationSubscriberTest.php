<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Functional\Profile;

use AlexFigures\Symfony\Bridge\Symfony\EventSubscriber\ProfileNegotiationSubscriber;
use AlexFigures\Symfony\Profile\Negotiation\ProfileNegotiator;
use AlexFigures\Symfony\Profile\ProfileContext;
use AlexFigures\Symfony\Profile\ProfileRegistry;
use AlexFigures\Symfony\Tests\Util\FakeProfile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(ProfileNegotiationSubscriber::class)]
final class ProfileNegotiationSubscriberTest extends TestCase
{
    public function testSubscriberStoresContextAndDecoratesResponse(): void
    {
        $profile = new FakeProfile('https://profiles.test/a');
        $registry = new ProfileRegistry([$profile]);
        $negotiator = new ProfileNegotiator(
            $registry,
            ['https://profiles.test/a']
        );
        $subscriber = new ProfileNegotiationSubscriber($negotiator);

        $kernel = $this->createKernel();
        $request = Request::create('/articles', 'GET');

        $requestEvent = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($requestEvent);

        $response = new Response('', 200, ['Content-Type' => 'application/vnd.api+json; charset=utf-8']);
        $responseEvent = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $subscriber->onKernelResponse($responseEvent);

        self::assertSame('<https://profiles.test/a>; rel="profile"', $response->headers->get('Link'));
        self::assertSame('application/vnd.api+json; charset=utf-8; profile="https://profiles.test/a"', $response->headers->get('Content-Type'));
    }

    public function testSubscriberRespectsNegotiatorConfiguration(): void
    {
        $profile = new FakeProfile('https://profiles.test/a');
        $registry = new ProfileRegistry([$profile]);
        $negotiator = new ProfileNegotiator(
            $registry,
            ['https://profiles.test/a'],
            [],
            [
                'echo_profiles_in_content_type' => false,
                'link_header' => false,
            ]
        );
        $subscriber = new ProfileNegotiationSubscriber($negotiator);

        $kernel = $this->createKernel();
        $request = Request::create('/articles', 'GET');
        ProfileContext::store($request, new ProfileContext([
            $profile->uri() => $profile,
        ]));

        $response = new Response('', 200, ['Content-Type' => 'application/vnd.api+json']);
        $responseEvent = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $subscriber->onKernelResponse($responseEvent);

        self::assertFalse($response->headers->has('Link'));
        self::assertSame('application/vnd.api+json', $response->headers->get('Content-Type'));
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
