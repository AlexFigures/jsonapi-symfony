<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Profile\Fixtures;

final readonly class InjectedProfileContext
{
    public function __construct(public string $uri, public bool $requireMissingField = false)
    {
    }
}
