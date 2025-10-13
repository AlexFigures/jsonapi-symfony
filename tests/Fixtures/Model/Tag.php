<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Fixtures\Model;

use AlexFigures\Symfony\Resource\Attribute\Attribute;
use AlexFigures\Symfony\Resource\Attribute\Id;
use AlexFigures\Symfony\Resource\Attribute\JsonApiResource;
use AlexFigures\Symfony\Resource\Attribute\SortableFields;

#[JsonApiResource(type: 'tags')]
#[SortableFields(['name'])]
final class Tag
{
    #[Id]
    #[Attribute]
    public string $id;

    #[Attribute]
    public string $name;

    public function __construct(string $id, string $name)
    {
        $this->id = $id;
        $this->name = $name;
    }
}
