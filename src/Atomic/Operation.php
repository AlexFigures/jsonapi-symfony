<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Atomic;

/**
 * @internal
 */
final class Operation
{
    public function __construct(
        public readonly string $op,
        public readonly ?Ref $ref,
        public readonly ?string $href,
        public readonly mixed $data,
        public readonly array $meta,
        public readonly string $pointer,
    ) {
    }

    public function isRelationshipOperation(): bool
    {
        return $this->ref?->relationship !== null;
    }

    public function requiresData(): bool
    {
        return $this->op !== 'remove';
    }
}
