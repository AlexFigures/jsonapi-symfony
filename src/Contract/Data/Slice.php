<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Contract\Data;

final class Slice
{
    /**
     * @param list<object> $items
     */
    public function __construct(
        public array $items,
        public int $pageNumber,
        public int $pageSize,
        public int $totalItems,
    ) {
    }
}
