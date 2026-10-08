<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Integration\Fixtures\CustomRoute;

use AlexFigures\JsonApi\CustomRoute\Context\CustomRouteContext;
use AlexFigures\JsonApi\CustomRoute\Handler\CustomRouteHandlerInterface;
use AlexFigures\JsonApi\CustomRoute\Result\CustomRouteResult;

/**
 * Test handler that returns an error result (instead of throwing exception).
 * This tests transaction rollback when handler returns error.
 */
final class ErrorReturningHandler implements CustomRouteHandlerInterface
{
    public function handle(CustomRouteContext $context): CustomRouteResult
    {
        return CustomRouteResult::badRequest('Validation failed');
    }
}
