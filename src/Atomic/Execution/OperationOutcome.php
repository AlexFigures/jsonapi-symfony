<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Atomic\Execution;

final class OperationOutcome
{
    public function __construct(
        public readonly bool $hasData,
        public readonly ?string $type = null,
        public readonly ?string $id = null,
        public readonly ?object $model = null,
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
