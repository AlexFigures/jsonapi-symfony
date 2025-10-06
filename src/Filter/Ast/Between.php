<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Filter\Ast;

/**
 * Represents a BETWEEN comparison.
 */
final class Between implements Node
{
    public function __construct(
        public readonly string $fieldPath,
        public readonly mixed $from,
        public readonly mixed $to,
    ) {
    }
}
