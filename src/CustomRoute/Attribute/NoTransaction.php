<?php

declare(strict_types=1);

namespace JsonApi\Symfony\CustomRoute\Attribute;

use Attribute;

/**
 * Marks a custom route handler to be executed without transaction wrapping.
 *
 * By default, all custom route handlers are executed within a database transaction.
 * If the handler throws an exception or returns an error result, the transaction
 * is automatically rolled back.
 *
 * Use this attribute on read-only handlers (e.g., search, filtering) to skip
 * transaction wrapping and improve performance.
 *
 * Example:
 * ```php
 * #[NoTransaction]
 * final class SearchArticlesHandler implements CustomRouteHandlerInterface
 * {
 *     public function handle(CustomRouteContext $context): CustomRouteResult
 *     {
 *         // Read-only operation, no transaction needed
 *         $results = $this->searchService->search($query);
 *         return CustomRouteResult::collection($results);
 *     }
 * }
 * ```
 *
 * @api This attribute is part of the public API and follows semantic versioning.
 * @since 0.3.0
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class NoTransaction
{
}
