<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Filter\Ast;

/**
 * Represents an IS NULL / IS NOT NULL check.
 * @api
 */
final readonly class NullCheck implements Node
{
    public function __construct(
        public string $fieldPath,
        public bool $isNull,
    ) {
    }
}
