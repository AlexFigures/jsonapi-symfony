<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Fixtures\CustomRoute;

use AlexFigures\Symfony\CustomRoute\Context\CustomRouteContext;
use AlexFigures\Symfony\CustomRoute\Handler\CustomRouteHandlerInterface;
use AlexFigures\Symfony\CustomRoute\Result\CustomRouteResult;

/**
 * Test handler that returns a DTO without an id property.
 *
 * This demonstrates the fix for the regression where update events
 * were not dispatched when handlers returned DTOs/value objects
 * without a standard id property or getId() method.
 */
final class UpdateArticleWithDtoHandler implements CustomRouteHandlerInterface
{
    public function handle(CustomRouteContext $context): CustomRouteResult
    {
        $article = $context->getResource();
        $article->title = 'Updated Title';

        // Return a DTO without id property (simulates value object response)
        $dto = new class ($article->title) {
            public function __construct(
                public readonly string $title
            ) {
            }
        };

        return CustomRouteResult::resource($dto);
    }
}
