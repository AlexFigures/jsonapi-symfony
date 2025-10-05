<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Request;

final class SortingWhitelist
{
    /**
     * @param array<string, list<string>> $map
     */
    public function __construct(private array $map = [])
    {
    }

    /**
     * @return list<string>
     */
    public function allowedFor(string $type): array
    {
        $fields = $this->map[$type] ?? [];

        return array_values($fields);
    }
}
