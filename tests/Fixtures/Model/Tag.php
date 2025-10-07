<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Fixtures\Model;

use JsonApi\Symfony\Resource\Attribute\Attribute;
use JsonApi\Symfony\Resource\Attribute\Id;
use JsonApi\Symfony\Resource\Attribute\JsonApiResource;
use JsonApi\Symfony\Resource\Attribute\SortableFields;

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
