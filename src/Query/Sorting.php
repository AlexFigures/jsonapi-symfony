<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Query;

final class Sorting
{
    public function __construct(
        public string $field,
        public bool $desc,
    ) {
    }
}
