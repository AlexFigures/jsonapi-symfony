<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Filter\Validation;

/** @internal */
final readonly class FilterComplexity
{
    public function __construct(
        public int $depth,
        public int $nodes,
        public int $operands,
        public int $pathHops,
    ) {
    }
}
