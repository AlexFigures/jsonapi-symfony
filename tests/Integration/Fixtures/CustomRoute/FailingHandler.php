<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Integration\Fixtures\CustomRoute;

use JsonApi\Symfony\CustomRoute\Context\CustomRouteContext;
use JsonApi\Symfony\CustomRoute\Handler\CustomRouteHandlerInterface;
use JsonApi\Symfony\CustomRoute\Result\CustomRouteResult;
use RuntimeException;

/**
 * Test handler that throws an exception to test error handling.
 */
final class FailingHandler implements CustomRouteHandlerInterface
{
    public function handle(CustomRouteContext $context): CustomRouteResult
    {
        throw new RuntimeException('Handler failed intentionally');
    }
}
