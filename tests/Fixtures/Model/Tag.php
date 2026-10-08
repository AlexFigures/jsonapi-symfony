<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Fixtures\Model;

use AlexFigures\JsonApi\Resource\Attribute\Attribute;
use AlexFigures\JsonApi\Resource\Attribute\Id;
use AlexFigures\JsonApi\Resource\Attribute\JsonApiResource;
use AlexFigures\JsonApi\Resource\Attribute\SortableFields;

#[JsonApiResource(type: 'tags')]
#[SortableFields(['name'])]
final class Tag
{
    public function __construct(
        #[Id]
        #[Attribute]
        public string $id,
        #[Attribute]
        public string $name
    ) {
    }
}
