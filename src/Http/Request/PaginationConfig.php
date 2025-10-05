<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Request;

final class PaginationConfig
{
    public function __construct(
        public int $defaultSize = 25,
        public int $maxSize = 100,
    ) {
    }
}
