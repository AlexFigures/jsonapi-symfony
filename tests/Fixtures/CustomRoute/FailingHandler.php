<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Fixtures\CustomRoute;

use JsonApi\Symfony\CustomRoute\Context\CustomRouteContext;
use JsonApi\Symfony\CustomRoute\Handler\CustomRouteHandlerInterface;
use JsonApi\Symfony\CustomRoute\Result\CustomRouteResult;

/**
 * Test handler that throws an exception (for testing error handling).
 */
final class FailingHandler implements CustomRouteHandlerInterface
{
    public function handle(CustomRouteContext $context): CustomRouteResult
    {
        throw new \RuntimeException('Simulated handler failure');
    }
}

