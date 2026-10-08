<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Fixtures\Model;

use AlexFigures\JsonApi\Resource\Attribute\Attribute;
use AlexFigures\JsonApi\Resource\Attribute\Id;
use AlexFigures\JsonApi\Resource\Attribute\JsonApiCustomRoute;
use AlexFigures\JsonApi\Resource\Attribute\JsonApiResource;

/**
 * Example entity with custom routes defined via attributes.
 */
#[JsonApiResource(type: 'custom-articles')]
#[JsonApiCustomRoute(
    name: 'articles.publish',
    path: '/articles/{id}/publish',
    methods: ['POST'],
    controller: 'App\Controller\PublishArticleController',
    description: 'Publish an article'
)]
#[JsonApiCustomRoute(
    name: 'articles.archive',
    path: '/articles/{id}/archive',
    methods: ['POST'],
    controller: 'App\Controller\ArchiveArticleController',
    requirements: ['id' => '\d+'],
    priority: 5
)]
final class ArticleWithCustomRoutes
{
    #[Attribute]
    public bool $published = false;

    public function __construct(
        #[Id]
        #[Attribute]
        public string $id,
        #[Attribute]
        public string $title,
        #[Attribute]
        public string $content
    ) {
    }
}
