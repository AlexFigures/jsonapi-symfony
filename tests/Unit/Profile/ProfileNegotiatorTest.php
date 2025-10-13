<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Profile;

use AlexFigures\Symfony\Http\Exception\NotAcceptableException;
use AlexFigures\Symfony\Profile\Negotiation\ProfileNegotiator;
use AlexFigures\Symfony\Profile\ProfileContext;
use AlexFigures\Symfony\Profile\ProfileRegistry;
use AlexFigures\Symfony\Tests\Util\FakeProfile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(ProfileNegotiator::class)]
final class ProfileNegotiatorTest extends TestCase
{
    public function testNegotiationCombinesSourcesAndPerTypeProfiles(): void
    {
        $profileA = new FakeProfile('https://profiles.test/a');
        $profileB = new FakeProfile('https://profiles.test/b');
        $profileC = new FakeProfile('https://profiles.test/c');
        $profileD = new FakeProfile('https://profiles.test/d');

        $registry = new ProfileRegistry([$profileA, $profileB, $profileC, $profileD]);
        $negotiator = new ProfileNegotiator(
            $registry,
            ['https://profiles.test/a'],
            ['articles' => ['https://profiles.test/d']],
            ['require_known_profiles' => false]
        );

        $request = Request::create('/articles', 'GET', server: [
            'CONTENT_TYPE' => 'application/vnd.api+json; profile="https://profiles.test/c https://profiles.test/unknown"',
            'HTTP_ACCEPT' => 'application/vnd.api+json; profile="!https://profiles.test/a https://profiles.test/b"',
        ]);

        $context = $negotiator->negotiate($request);

        self::assertInstanceOf(ProfileContext::class, $context);
        self::assertSame([
            'https://profiles.test/c',
            'https://profiles.test/b',
        ], $context->activeUris());
        self::assertSame([
            'default' => ['https://profiles.test/a'],
            'content-type' => ['https://profiles.test/c'],
            'accept' => ['https://profiles.test/b'],
            'disabled' => ['https://profiles.test/a'],
        ], $context->sources());

        $profiles = $context->profiles();
        self::assertCount(2, $profiles);
        self::assertSame('https://profiles.test/c', $profiles[0]->uri());
        self::assertSame('https://profiles.test/b', $profiles[1]->uri());

        $perType = $context->profilesForType('articles');
        self::assertCount(3, $perType);
        self::assertSame('https://profiles.test/c', $perType[0]->uri());
        self::assertSame('https://profiles.test/b', $perType[1]->uri());
        self::assertSame('https://profiles.test/d', $perType[2]->uri());

        self::assertSame('https://profiles.test/b', $context->profilesForType('comments')[1]->uri());

        self::assertTrue($negotiator->shouldEchoProfilesInContentType());
        self::assertTrue($negotiator->shouldEmitLinkHeader());
    }

    public function testRequireKnownProfilesTriggersException(): void
    {
        $registry = new ProfileRegistry([]);
        $negotiator = new ProfileNegotiator($registry, [], [], ['require_known_profiles' => true]);

        $request = Request::create('/articles', 'GET', server: [
            'HTTP_ACCEPT' => 'application/vnd.api+json; profile="https://profiles.test/unknown"',
        ]);

        $this->expectException(NotAcceptableException::class);
        $this->expectExceptionMessage('Unknown profile(s) requested: https://profiles.test/unknown');

        $negotiator->negotiate($request);
    }
}
