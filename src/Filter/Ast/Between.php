<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Filter\Ast;

/**
 * Represents a BETWEEN comparison.
 * @api
 */
final readonly class Between implements Node
{
    public function __construct(
        public string $fieldPath,
        public mixed $from,
        public mixed $to,
    ) {
    }
}
