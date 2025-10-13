<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Atomic;

use AlexFigures\Symfony\Atomic\AtomicConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AtomicConfig::class)]
final class AtomicConfigTest extends TestCase
{
    public function testDefaultEnabledIsFalse(): void
    {
        $config = new AtomicConfig();

        self::assertFalse($config->enabled);
    }

    public function testDefaultEndpointIsApiOperations(): void
    {
        $config = new AtomicConfig();

        self::assertSame('/api/operations', $config->endpoint);
    }

    public function testDefaultRequireExtHeaderIsTrue(): void
    {
        $config = new AtomicConfig();

        self::assertTrue($config->requireExtHeader);
    }

    public function testDefaultMaxOperationsIs100(): void
    {
        $config = new AtomicConfig();

        self::assertSame(100, $config->maxOperations);
    }

    public function testDefaultReturnPolicyIsAuto(): void
    {
        $config = new AtomicConfig();

        self::assertSame('auto', $config->returnPolicy);
    }

    public function testDefaultAllowHrefIsTrue(): void
    {
        $config = new AtomicConfig();

        self::assertTrue($config->allowHref);
    }

    public function testDefaultLidInResourceAndIdentifierIsTrue(): void
    {
        $config = new AtomicConfig();

        self::assertTrue($config->lidInResourceAndIdentifier);
    }

    public function testDefaultRoutePrefixIsApi(): void
    {
        $config = new AtomicConfig();

        self::assertSame('/api', $config->routePrefix);
    }

    public function testCanOverrideEnabled(): void
    {
        $config = new AtomicConfig(enabled: true);

        self::assertTrue($config->enabled);
    }

    public function testCanOverrideEndpoint(): void
    {
        $config = new AtomicConfig(endpoint: '/custom/operations');

        self::assertSame('/custom/operations', $config->endpoint);
    }

    public function testCanOverrideRequireExtHeader(): void
    {
        $config = new AtomicConfig(requireExtHeader: false);

        self::assertFalse($config->requireExtHeader);
    }

    public function testCanOverrideMaxOperations(): void
    {
        $config = new AtomicConfig(maxOperations: 50);

        self::assertSame(50, $config->maxOperations);
    }

    public function testCanOverrideReturnPolicy(): void
    {
        $config = new AtomicConfig(returnPolicy: 'all');

        self::assertSame('all', $config->returnPolicy);
    }

    public function testCanOverrideAllowHref(): void
    {
        $config = new AtomicConfig(allowHref: false);

        self::assertFalse($config->allowHref);
    }

    public function testCanOverrideLidInResourceAndIdentifier(): void
    {
        $config = new AtomicConfig(lidInResourceAndIdentifier: false);

        self::assertFalse($config->lidInResourceAndIdentifier);
    }

    public function testCanOverrideRoutePrefix(): void
    {
        $config = new AtomicConfig(routePrefix: '/v1');

        self::assertSame('/v1', $config->routePrefix);
    }

    public function testCanOverrideAllParameters(): void
    {
        $config = new AtomicConfig(
            enabled: true,
            endpoint: '/custom/ops',
            requireExtHeader: false,
            maxOperations: 200,
            returnPolicy: 'none',
            allowHref: false,
            lidInResourceAndIdentifier: false,
            routePrefix: '/v2'
        );

        self::assertTrue($config->enabled);
        self::assertSame('/custom/ops', $config->endpoint);
        self::assertFalse($config->requireExtHeader);
        self::assertSame(200, $config->maxOperations);
        self::assertSame('none', $config->returnPolicy);
        self::assertFalse($config->allowHref);
        self::assertFalse($config->lidInResourceAndIdentifier);
        self::assertSame('/v2', $config->routePrefix);
    }
}
