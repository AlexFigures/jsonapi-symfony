<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Query;

final class Pagination
{
    public function __construct(
        public int $number,
        public int $size,
    ) {
    }
}
