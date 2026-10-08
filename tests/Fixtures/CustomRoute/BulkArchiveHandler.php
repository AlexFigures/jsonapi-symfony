<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Fixtures\CustomRoute;

use AlexFigures\JsonApi\CustomRoute\Context\CustomRouteContext;
use AlexFigures\JsonApi\CustomRoute\Handler\CustomRouteHandlerInterface;
use AlexFigures\JsonApi\CustomRoute\Result\CustomRouteResult;

/**
 * Test handler for bulk archiving articles.
 */
final readonly class BulkArchiveHandler implements CustomRouteHandlerInterface
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
