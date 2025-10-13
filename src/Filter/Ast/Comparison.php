<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Filter\Ast;

/**
 * Represents a primitive comparison like eq, lt, etc.
 */
final class Comparison implements Node
{
    public function __construct(
        public readonly string $fieldPath,
        public readonly string $operator,
        /** @var list<mixed> */
        public readonly array $values,
    ) {
    }
}
