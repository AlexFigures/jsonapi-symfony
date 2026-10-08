<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Integration\Fixtures;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\DBAL\Tools\DsnParser;

final class ConnectionFactory
{
    /** @var \WeakMap<Connection, QueryLogger>|null */
    private static ?\WeakMap $loggers = null;

    public static function create(array $parameters, ?Configuration $configuration = null): Connection
    {
        if (isset($parameters['url'])) {
            $parsed = (new DsnParser(['mysql' => 'pdo_mysql', 'postgres' => 'pdo_pgsql', 'postgresql' => 'pdo_pgsql', 'sqlite' => 'pdo_sqlite']))->parse($parameters['url']);
            unset($parameters['url']);
            $parameters = $parameters + $parsed;
        }
        $configuration ??= new Configuration();
        $logger = new QueryLogger();
        $configuration->setMiddlewares([...$configuration->getMiddlewares(), new Middleware($logger)]);
        $connection = DriverManager::getConnection($parameters, $configuration);
        self::$loggers ??= new \WeakMap();
        self::$loggers[$connection] = $logger;
        return $connection;
    }

    public static function observe(Connection $connection, object $observer): void
    {
        if (self::$loggers === null || !isset(self::$loggers[$connection])) {
            throw new \LogicException('Test connection must be created with ConnectionFactory before observation.');
        }
        self::$loggers[$connection]->observer = $observer;
    }
}
