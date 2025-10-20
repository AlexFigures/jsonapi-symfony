<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Fixtures\Model;

use AlexFigures\Symfony\Resource\Attribute\Attribute;
use AlexFigures\Symfony\Resource\Attribute\Id;
use AlexFigures\Symfony\Resource\Attribute\JsonApiResource;

#[JsonApiResource(type: 'blog_posts')]
final class BlogPost
{
    #[Id]
    #[Attribute]
    public string $id;

    #[Attribute]
    public string $title;

    public function __construct(string $id, string $title)
    {
        $this->id = $id;
        $this->title = $title;
    }
}

