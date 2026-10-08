<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Resource\Definition;

/** @api */
enum ReadProjection: string
{
    case ENTITY = 'entity';
    case DTO = 'dto';
    case CUSTOM = 'custom';
}
