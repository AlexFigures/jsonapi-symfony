<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Atomic;

/**
 * @internal
 */
final readonly class Ref
{
    public function __construct(
        public string $type,
        public ?string $id,
        public ?string $lid,
        public ?string $relationship,
    ) {
    }

    public function hasIdentifier(): bool
    {
        return $this->id !== null || $this->lid !== null;
    }

    public function pointerSegment(): string
    {
        return $this->relationship === null ? 'ref' : 'ref/relationship';
    }
}
