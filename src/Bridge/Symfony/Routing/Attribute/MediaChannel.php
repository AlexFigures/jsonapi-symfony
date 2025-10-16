<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Bridge\Symfony\Routing\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class MediaChannel
{
    public const REQUEST_ATTRIBUTE = 'jsonapi_media_channel';

    public function __construct(public readonly string $name)
    {
    }
}
