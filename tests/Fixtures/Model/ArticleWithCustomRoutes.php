<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Fixtures\Model;

use AlexFigures\Symfony\Resource\Attribute\Attribute;
use AlexFigures\Symfony\Resource\Attribute\Id;
use AlexFigures\Symfony\Resource\Attribute\JsonApiCustomRoute;
use AlexFigures\Symfony\Resource\Attribute\JsonApiResource;

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
    #[Id]
    #[Attribute]
    public string $id;

    #[Attribute]
    public string $title;

    #[Attribute]
    public string $content;

    #[Attribute]
    public bool $published = false;

    public function __construct(string $id, string $title, string $content)
    {
        $this->id = $id;
        $this->title = $title;
        $this->content = $content;
    }
}
