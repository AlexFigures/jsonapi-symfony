<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Atomic;

/**
 * @internal
 */
final readonly class Operation
{
    /**
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public string $op,
        public ?Ref $ref,
        public ?string $href,
        public mixed $data,
        public array $meta,
        public string $pointer,
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
