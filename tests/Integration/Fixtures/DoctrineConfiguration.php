<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Integration\Fixtures;

use Doctrine\ORM\Configuration;

final class DoctrineConfiguration
{
    public static function configureLazyObjects(Configuration $configuration): void
    {
        // Symfony 8 removes LazyGhostTrait; ORM native lazy objects require PHP 8.4.
        if (\PHP_VERSION_ID >= 80400 && method_exists($configuration, 'enableNativeLazyObjects')) {
            $configuration->enableNativeLazyObjects(true);
        }
    }
}
