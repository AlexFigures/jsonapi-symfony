<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Atomic;

/**
 * @internal
 */
final class Ref
{
    public function __construct(
        public readonly string $type,
        public readonly ?string $id,
        public readonly ?string $lid,
        public readonly ?string $relationship,
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
