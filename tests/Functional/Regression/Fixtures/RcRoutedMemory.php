<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Functional\Regression\Fixtures;

use AlexFigures\JsonApi\Resource\Attribute\Attribute;
use AlexFigures\JsonApi\Resource\Attribute\Id;
use AlexFigures\JsonApi\Resource\Attribute\JsonApiResource;

#[JsonApiResource(type: 'rc-routed', routePrefix: '/reference/')]
final class RcRoutedMemory
{
    public function __construct(#[Id] public string $id, #[Attribute] public string $title)
    {
    }
}
