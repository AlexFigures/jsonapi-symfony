<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Integration\Fixtures\CustomRoute;

use JsonApi\Symfony\CustomRoute\Context\CustomRouteContext;
use JsonApi\Symfony\CustomRoute\Handler\CustomRouteHandlerInterface;
use JsonApi\Symfony\CustomRoute\Result\CustomRouteResult;

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

