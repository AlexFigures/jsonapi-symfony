<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\ReadPath;

final class MySQLReadPathTest extends DoctrineReadPathTestCase
{
    protected function getDatabaseUrl(): string
    {
        return $_ENV['DATABASE_URL_MYSQL'] ?? 'mysql://jsonapi:secret@mysql:3306/jsonapi_test';
    }

    protected function getPlatform(): string
    {
        return 'mysql';
    }
}
