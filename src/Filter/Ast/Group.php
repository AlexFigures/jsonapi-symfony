<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Filter\Ast;

/**
 * Represents a grouped sub-expression, preserving explicit parentheses.
 * @api
 */
final readonly class Group implements Node
{
    public function __construct(
        public Node $expression,
    ) {
    }
}
