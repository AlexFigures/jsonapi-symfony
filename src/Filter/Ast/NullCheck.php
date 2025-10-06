<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Filter\Ast;

/**
 * Represents an IS NULL / IS NOT NULL check.
 */
final class NullCheck implements Node
{
    public function __construct(
        public readonly string $fieldPath,
        public readonly bool $isNull,
    ) {
    }
}
