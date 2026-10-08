<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Fixtures\CustomRoute;

use AlexFigures\JsonApi\CustomRoute\Context\CustomRouteContext;
use AlexFigures\JsonApi\CustomRoute\Handler\CustomRouteHandlerInterface;
use AlexFigures\JsonApi\CustomRoute\Result\CustomRouteResult;

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
