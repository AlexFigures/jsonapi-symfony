<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Query;

use InvalidArgumentException;

final class Sorting
{
    public bool $desc;

    public function __construct(
        public string $field,
        bool|string $direction,
    ) {
        $this->desc = $this->normalizeDirection($direction);
    }

    public function direction(): string
    {
        return $this->desc ? 'DESC' : 'ASC';
    }

    private function normalizeDirection(bool|string $direction): bool
    {
        if (is_bool($direction)) {
            return $direction;
        }

        $normalized = strtoupper($direction);

        return match ($normalized) {
            'ASC' => false,
            'DESC' => true,
            default => throw new InvalidArgumentException(sprintf(
                'Unsupported sorting direction "%s". Expected "ASC" or "DESC".',
                $direction,
            )),
        };
    }
}
