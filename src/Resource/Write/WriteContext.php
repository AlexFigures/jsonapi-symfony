<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Resource\Write;

final class WriteContext
{
    public function __construct(
        public readonly ?object $user = null,
        public readonly array $options = [],
    ) {
    }
}
