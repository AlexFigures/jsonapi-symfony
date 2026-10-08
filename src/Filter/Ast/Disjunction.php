<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Filter\Ast;

/**
 * Represents an OR node combining child filters.
 * @api
 */
final readonly class Disjunction implements Node
{
    /**
     * @param list<Node> $children
     */
    public function __construct(
        public array $children,
    ) {
    }
}
