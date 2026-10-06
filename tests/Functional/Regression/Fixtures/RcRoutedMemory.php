<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Functional\Regression\Fixtures;

use AlexFigures\Symfony\Resource\Attribute\Attribute;
use AlexFigures\Symfony\Resource\Attribute\Id;
use AlexFigures\Symfony\Resource\Attribute\JsonApiResource;

#[JsonApiResource(type: 'rc-routed', routePrefix: '/reference/')]
final class RcRoutedMemory
{
    public function __construct(#[Id] public string $id, #[Attribute] public string $title)
    {
    }
}
