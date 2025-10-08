<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Fixtures\Model;

use JsonApi\Symfony\Resource\Attribute\JsonApiCustomRoute;

/**
 * Example controller with custom routes defined via attributes.
 */
#[JsonApiCustomRoute(
    name: 'articles.search',
    path: '/articles/search',
    methods: ['GET'],
    controller: 'JsonApi\Symfony\Tests\Fixtures\Model\SearchController::search',
    resourceType: 'custom-articles',
    description: 'Search articles by query'
)]
#[JsonApiCustomRoute(
    name: 'articles.trending',
    path: '/articles/trending',
    methods: ['GET'],
    controller: 'JsonApi\Symfony\Tests\Fixtures\Model\SearchController::trending',
    resourceType: 'custom-articles',
    defaults: ['_format' => 'json'],
    priority: 10
)]
final class SearchController
{
    public function search(): void
    {
        // Search implementation
    }

    public function trending(): void
    {
        // Trending implementation
    }
}
