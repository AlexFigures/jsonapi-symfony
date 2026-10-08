<?php

declare(strict_types=1);

use AlexFigures\JsonApi\Tests\Integration\Fixtures\ConnectionFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

foreach (['DATABASE_URL_POSTGRES', 'DATABASE_URL_PGSQL', 'DATABASE_URL_MYSQL', 'DATABASE_URL_MARIADB', 'DATABASE_URL_SQLITE'] as $name) {
    $url = getenv($name);
    if ($url === false || $url === '') {
        fwrite(\STDERR, $name . " must be configured before integration tests.\n");
        exit(1);
    }

    try {
        $connection = ConnectionFactory::create(['url' => $url]);
        $connection->executeQuery('SELECT 1')->free();
        $connection->close();
        fwrite(\STDOUT, $name . ": connection OK\n");
    } catch (Throwable $exception) {
        fwrite(\STDERR, $name . ': connection failed (' . $exception::class . "). Check the database service, TCP port and test credentials.\n");
        exit(1);
    }
}
