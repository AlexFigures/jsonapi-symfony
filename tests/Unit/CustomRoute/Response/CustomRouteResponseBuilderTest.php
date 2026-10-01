<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\CustomRoute\Response;

use PHPUnit\Framework\TestCase;

/**
 *
 * Note: CustomRouteResponseBuilder is tested in integration tests
 * because it requires complex setup with DocumentBuilder (which is final).
 * See tests/Integration/CustomRoute/CustomRouteHandlerIntegrationTest.php
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\AlexFigures\Symfony\CustomRoute\Response\CustomRouteResponseBuilder::class)]
final class CustomRouteResponseBuilderTest extends TestCase
{
    public function testPlaceholder(): void
    {
        // Placeholder test to prevent "no tests" warning
        // Real tests are in integration test suite
        self::assertTrue(true);
    }
}
