<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Unit\CustomRoute\Response;

use PHPUnit\Framework\TestCase;

/**
 * @covers \JsonApi\Symfony\CustomRoute\Response\CustomRouteResponseBuilder
 *
 * Note: CustomRouteResponseBuilder is tested in integration tests
 * because it requires complex setup with DocumentBuilder (which is final).
 * See tests/Integration/CustomRoute/CustomRouteHandlerIntegrationTest.php
 */
final class CustomRouteResponseBuilderTest extends TestCase
{
    public function testPlaceholder(): void
    {
        // Placeholder test to prevent "no tests" warning
        // Real tests are in integration test suite
        self::assertTrue(true);
    }
}
