<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Unit\Bridge\Doctrine\Instantiator\Fixtures;

final class YamlWriteModel
{
    public string $protectedValue = 'original';

    public function __construct(public string $title)
    {
    }
}
