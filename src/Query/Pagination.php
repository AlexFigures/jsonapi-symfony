<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Query;

/** @api */
final class Pagination
{
    public function __construct(
        public int $number,
        public int $size,
    ) {
    }
}
