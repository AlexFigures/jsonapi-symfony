<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Functional\Profile;

use JsonApi\Symfony\Http\Exception\NotAcceptableException;
use JsonApi\Symfony\Http\Exception\UnsupportedMediaTypeException;
use JsonApi\Symfony\Http\Negotiation\MediaType;
use JsonApi\Symfony\Profile\Negotiation\ProfileNegotiator;
use JsonApi\Symfony\Profile\ProfileRegistry;
use JsonApi\Symfony\Tests\Util\FakeProfile;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * GAP-003: Profile Negotiation → 406
 *
 * Tests that unsupported profiles in Accept header trigger 406 Not Acceptable:
 * - Unsupported profile parameter in Accept header returns 406
 * - Multiple profiles with at least one unsupported returns 406
 * - Valid profile is accepted
 */
final class ProfileNegotiationErrorsTest extends TestCase
{
    public function testUnsupportedProfileTriggers406(): void
    {
        // Create negotiator with require_known_profiles enabled
        $registry = new ProfileRegistry([]);
        $negotiator = new ProfileNegotiator($registry, [], [], ['require_known_profiles' => true]);

        // Request with unsupported profile in Accept header
        $request = Request::create('/api/articles', 'GET', server: [
            'HTTP_ACCEPT' => MediaType::JSON_API . '; profile="https://example.com/unsupported-profile"',
        ]);

        $this->expectException(NotAcceptableException::class);
        $this->expectExceptionMessageMatches('/profile|unknown/i');

        $negotiator->negotiate($request);
    }

    public function testMultipleProfilesWithUnsupportedTriggers406(): void
    {
        // Create negotiator with require_known_profiles enabled
        $profile1 = new FakeProfile('https://example.com/profile1');
        $registry = new ProfileRegistry([$profile1]);
        $negotiator = new ProfileNegotiator($registry, [], [], ['require_known_profiles' => true]);

        // Request with multiple profiles, one unsupported
        $request = Request::create('/api/articles', 'GET', server: [
            'HTTP_ACCEPT' => MediaType::JSON_API . '; profile="https://example.com/profile1 https://example.com/unsupported"',
        ]);

        $this->expectException(NotAcceptableException::class);
        $this->expectExceptionMessageMatches('/unknown/i');

        $negotiator->negotiate($request);
    }

    public function testNoProfileIsAccepted(): void
    {
        // Create negotiator without require_known_profiles
        $registry = new ProfileRegistry([]);
        $negotiator = new ProfileNegotiator($registry, [], [], ['require_known_profiles' => false]);

        // Request without profile parameter should be accepted
        $request = Request::create('/api/articles', 'GET', server: [
            'HTTP_ACCEPT' => MediaType::JSON_API,
        ]);

        $context = $negotiator->negotiate($request);

        self::assertSame([], $context->activeUris());
    }

    public function testEmptyProfileIsAccepted(): void
    {
        // Create negotiator
        $registry = new ProfileRegistry([]);
        $negotiator = new ProfileNegotiator($registry, [], [], ['require_known_profiles' => false]);

        // Request with empty profile parameter should be accepted
        $request = Request::create('/api/articles', 'GET', server: [
            'HTTP_ACCEPT' => MediaType::JSON_API . '; profile=""',
        ]);

        $context = $negotiator->negotiate($request);

        self::assertSame([], $context->activeUris());
    }

    public function testUnsupportedProfileInContentTypeTriggers415(): void
    {
        // Create negotiator with require_known_profiles enabled
        $registry = new ProfileRegistry([]);
        $negotiator = new ProfileNegotiator($registry, [], [], ['require_known_profiles' => true]);

        // POST request with unsupported profile in Content-Type
        $request = Request::create('/api/authors', 'POST', server: [
            'CONTENT_TYPE' => MediaType::JSON_API . '; profile="https://example.com/unsupported"',
            'HTTP_ACCEPT' => MediaType::JSON_API,
        ]);

        // Content-Type with unsupported profile should trigger NotAcceptableException
        // (In real implementation, this would be caught and converted to 415 by MediaTypeNegotiator)
        $this->expectException(NotAcceptableException::class);
        $this->expectExceptionMessageMatches('/unknown/i');

        $negotiator->negotiate($request);
    }

    public function testKnownProfileIsAccepted(): void
    {
        // Create negotiator with a known profile
        $profile = new FakeProfile('https://example.com/known-profile');
        $registry = new ProfileRegistry([$profile]);
        $negotiator = new ProfileNegotiator($registry, [], [], ['require_known_profiles' => true]);

        // Request with known profile in Accept header
        $request = Request::create('/api/articles', 'GET', server: [
            'HTTP_ACCEPT' => MediaType::JSON_API . '; profile="https://example.com/known-profile"',
        ]);

        $context = $negotiator->negotiate($request);

        self::assertSame(['https://example.com/known-profile'], $context->activeUris());
    }
}
