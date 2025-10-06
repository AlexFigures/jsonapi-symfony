<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Filter\Validation;

final class FilterLimits
{
    public function __construct(
        public readonly int $maxClauses,
        public readonly int $maxDepth,
        public readonly int $maxInValues,
    ) {
    }
}
