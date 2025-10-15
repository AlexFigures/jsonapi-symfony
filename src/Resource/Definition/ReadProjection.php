<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Resource\Definition;

enum ReadProjection: string
{
    case ENTITY = 'entity';
    case DTO = 'dto';
    case CUSTOM = 'custom';
}
