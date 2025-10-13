<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Fixtures\CustomRoute;

use AlexFigures\Symfony\CustomRoute\Context\CustomRouteContext;
use AlexFigures\Symfony\CustomRoute\Handler\CustomRouteHandlerInterface;
use AlexFigures\Symfony\CustomRoute\Result\CustomRouteResult;

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
