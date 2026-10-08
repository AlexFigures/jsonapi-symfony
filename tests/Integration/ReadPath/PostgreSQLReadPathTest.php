<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Integration\ReadPath;

final class PostgreSQLReadPathTest extends DoctrineReadPathTestCase
{
    protected function getDatabaseUrl(): string
    {
        return $_ENV['DATABASE_URL_POSTGRES'] ?? 'postgresql://jsonapi:secret@postgres:5432/jsonapi_test';
    }

    protected function getPlatform(): string
    {
        return 'postgresql';
    }
}
