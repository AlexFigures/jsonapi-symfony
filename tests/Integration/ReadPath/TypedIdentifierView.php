<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\ReadPath;

use Symfony\Component\Uid\Uuid;

final readonly class TypedIdentifierView
{
    public function __construct(public Uuid $identifier, public string $name)
    {
    }
}
