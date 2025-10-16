<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Bridge\Symfony\Negotiation;

use AlexFigures\Symfony\Bridge\Symfony\Negotiation\ChannelScopeMatcher;
use AlexFigures\Symfony\Bridge\Symfony\Routing\Attribute\MediaChannel;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class ChannelScopeMatcherTest extends TestCase
{
    public function testMatchesWhenScopeEmpty(): void
    {
        $matcher = new ChannelScopeMatcher();
        $request = Request::create('/api/articles');

        self::assertTrue($matcher->matches($request, []));
    }

    public function testMatchesByPathPrefix(): void
    {
        $matcher = new ChannelScopeMatcher();
        $request = Request::create('/sandbox/demo');

        self::assertTrue($matcher->matches($request, ['path_prefix' => '^/sandbox']));
        self::assertFalse($matcher->matches($request, ['path_prefix' => '^/api']));
    }

    public function testMatchesByRouteName(): void
    {
        $matcher = new ChannelScopeMatcher();
        $request = Request::create('/api/articles');
        $request->attributes->set('_route', 'docs_index');

        self::assertTrue($matcher->matches($request, ['route_name' => '^docs_']));
        self::assertFalse($matcher->matches($request, ['route_name' => '^api_']));
    }

    public function testMatchesByAttribute(): void
    {
        $matcher = new ChannelScopeMatcher();
        $request = Request::create('/api/articles');
        $request->attributes->set(MediaChannel::REQUEST_ATTRIBUTE, 'sandbox');

        self::assertTrue($matcher->matches($request, ['attribute' => '^sandbox$']));
        self::assertFalse($matcher->matches($request, ['attribute' => '^docs$']));
    }

    public function testInvalidPatternThrows(): void
    {
        $matcher = new ChannelScopeMatcher();
        $request = Request::create('/api/articles');

        $this->expectException(InvalidArgumentException::class);
        $matcher->matches($request, ['path_prefix' => '[/']);
    }
}
