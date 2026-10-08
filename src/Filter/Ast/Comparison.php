<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Filter\Ast;

/**
 * Represents a primitive comparison like eq, lt, etc.
 * @api
 */
final readonly class Comparison implements Node
{
    public function __construct(
        public string $fieldPath,
        public string $operator,
        /** @var list<mixed> */
        public array $values,
    ) {
    }
}
