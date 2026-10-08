<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Atomic\Execution;

/** @internal */
final readonly class OperationOutcome
{
    public function __construct(
        public bool $hasData,
        public ?string $type = null,
        public ?string $id = null,
        public ?object $model = null,
    ) {
    }

    public static function empty(): self
    {
        return new self(false);
    }

    public static function forResource(string $type, string $id, object $model): self
    {
        return new self(true, $type, $id, $model);
    }
}
