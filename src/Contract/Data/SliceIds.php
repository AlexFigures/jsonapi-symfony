<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Contract\Data;

final class SliceIds
{
    /**
     * @param list<string> $ids
     */
    public function __construct(
        public array $ids,
        public int $pageNumber,
        public int $pageSize,
        public int $totalItems,
    ) {
    }
}
