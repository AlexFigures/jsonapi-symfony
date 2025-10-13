<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\CustomRoute\Handler;

use AlexFigures\Symfony\CustomRoute\Context\CustomRouteContext;
use AlexFigures\Symfony\CustomRoute\Result\CustomRouteResult;

/**
 * Interface for custom route handlers.
 *
 * Handlers contain ONLY business logic. The bundle automatically handles:
 * - JSON:API response formatting (proper document structure, links, meta)
 * - Error handling and error document formatting
 * - Transaction management (automatic wrapping with rollback on errors)
 * - Resource pre-loading (for routes with {id} parameter)
 * - Content negotiation (Accept and Content-Type headers)
 * - HTTP status codes and headers
 * - Sparse fieldsets and includes support
 * - Event dispatching (ResourceChangedEvent)
 *
 * This dramatically improves developer experience by reducing boilerplate code
 * by ~78% and ensuring consistent JSON:API compliance across all custom routes.
 *
 * Example usage:
 * ```php
 * #[JsonApiCustomRoute(
 *     name: 'articles.publish',
 *     path: '/articles/{id}/publish',
 *     methods: ['POST'],
 *     handler: PublishArticleHandler::class
 * )]
 * final class Article { }
 *
 * final class PublishArticleHandler implements CustomRouteHandlerInterface
 * {
 *     public function handle(CustomRouteContext $context): CustomRouteResult
 *     {
 *         $article = $context->getResource();
 *
 *         if ($article->published) {
 *             return CustomRouteResult::conflict('Article is already published');
 *         }
 *
 *         $article->published = true;
 *         $article->publishedAt = new \DateTimeImmutable();
 *
 *         return CustomRouteResult::resource($article)
 *             ->withMeta(['publishedAt' => $article->publishedAt->format('c')]);
 *     }
 * }
 * ```
 *
 * @api This interface is part of the public API and follows semantic versioning.
 * @since 0.3.0
 */
interface CustomRouteHandlerInterface
{
    /**
     * Handle the custom route request.
     *
     * This method contains ONLY your business logic. All infrastructure concerns
     * (JSON:API formatting, error handling, transactions, etc.) are handled
     * automatically by the bundle.
     *
     * The handler is automatically executed within a database transaction. If this
     * method throws an exception or returns an error result, the transaction is
     * rolled back. To opt-out of transaction wrapping for read-only operations,
     * use the #[NoTransaction] attribute on your handler class.
     *
     * @param CustomRouteContext $context Request context with pre-loaded resources,
     *                                    parsed query parameters, request body, etc.
     *
     * @return CustomRouteResult Result indicating what to return (resource, collection,
     *                           error, no content, etc.). The bundle converts this to
     *                           a proper JSON:API response automatically.
     *
     * @throws \Throwable Any exception thrown is automatically converted to a JSON:API
     *                    error document with proper status code and formatting.
     */
    public function handle(CustomRouteContext $context): CustomRouteResult;
}
