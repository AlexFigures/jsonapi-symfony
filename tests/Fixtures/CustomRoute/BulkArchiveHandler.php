<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Fixtures\CustomRoute;

use AlexFigures\Symfony\CustomRoute\Context\CustomRouteContext;
use AlexFigures\Symfony\CustomRoute\Handler\CustomRouteHandlerInterface;
use AlexFigures\Symfony\CustomRoute\Result\CustomRouteResult;

/**
 * Test handler for bulk archiving articles.
 */
final class BulkArchiveHandler implements CustomRouteHandlerInterface
{
    public function __construct(
        private array $articles = []
    ) {
    }

    public function handle(CustomRouteContext $context): CustomRouteResult
    {
        $body = $context->getBody();
        $ids = $body['ids'] ?? [];

        if (empty($ids)) {
            return CustomRouteResult::badRequest('No article IDs provided');
        }

        $archived = 0;
        foreach ($ids as $id) {
            foreach ($this->articles as $article) {
                if ($article->id === $id) {
                    $article->archived = true;
                    $archived++;
                }
            }
        }

        return CustomRouteResult::noContent()
            ->withMeta([
                'archived' => $archived,
                'requested' => count($ids),
            ]);
    }
}
