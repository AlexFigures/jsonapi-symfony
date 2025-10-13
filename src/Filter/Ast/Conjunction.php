<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Filter\Ast;

/**
 * Represents an AND node combining child filters.
 */
final class Conjunction implements Node
{
    /**
     * @param list<Node> $children
     */
    public function __construct(
        public readonly array $children,
    ) {
    }
}
