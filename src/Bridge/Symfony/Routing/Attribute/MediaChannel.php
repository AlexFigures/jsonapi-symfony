<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Bridge\Symfony\Routing\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
/** @api */
final readonly class MediaChannel
{
    public const REQUEST_ATTRIBUTE = 'jsonapi_media_channel';

    public function __construct(public string $name)
    {
    }
}
