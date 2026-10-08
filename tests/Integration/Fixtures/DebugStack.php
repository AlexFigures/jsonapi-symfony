<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Integration\Fixtures;

final class DebugStack
{
    /** @var list<array{sql: string}> */
    public array $queries = [];

    public function startQuery(string $sql, ?array $params = null, ?array $types = null): void
    {
        $this->queries[] = ['sql' => $sql];
    }
}
