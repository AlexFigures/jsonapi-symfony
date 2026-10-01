<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Filter\Validation;

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
