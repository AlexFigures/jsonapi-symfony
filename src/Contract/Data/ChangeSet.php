<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Contract\Data;

final class ChangeSet
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(public array $attributes = [])
    {
    }
}
