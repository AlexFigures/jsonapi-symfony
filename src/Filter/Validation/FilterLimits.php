<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Filter\Validation;

/** @internal */
final readonly class FilterLimits
{
    public function __construct(
        public int $maxClauses,
        public int $maxDepth,
        public int $maxInValues,
    ) {
    }
}
