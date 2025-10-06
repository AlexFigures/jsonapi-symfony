<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Filter\Ast;

/**
 * Represents a grouped sub-expression, preserving explicit parentheses.
 */
final class Group implements Node
{
    public function __construct(
        public readonly Node $expression,
    ) {
    }
}
