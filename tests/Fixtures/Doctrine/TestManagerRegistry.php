<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Fixtures\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use RuntimeException;

/**
 * Minimal ManagerRegistry implementation for tests.
 */
final class TestManagerRegistry implements ManagerRegistry
{
    /**
     * @param array<string, EntityManagerInterface> $managers
     * @param array<string, string>                 $classMap Map of class => manager name
     */
    public function __construct(
        private array $managers,
        private array $classMap = [],
        private string $defaultManagerName = 'default',
    ) {
    }

    public function getDefaultConnectionName(): string
    {
        return 'default';
    }

    public function getConnection($name = null)
    {
        throw new RuntimeException('Connections are not supported in TestManagerRegistry.');
    }

    public function getConnections(): array
    {
        return [];
    }

    public function getConnectionNames(): array
    {
        return [];
    }

    public function getDefaultManagerName(): string
    {
        return $this->defaultManagerName;
    }

    public function getManager($name = null)
    {
        $name ??= $this->defaultManagerName;

        return $this->managers[$name] ?? null;
    }

    public function getManagers(): array
    {
        return $this->managers;
    }

    public function resetManager($name = null): void
    {
        throw new RuntimeException('resetManager is not supported in TestManagerRegistry.');
    }

    public function getAliasNamespace($alias)
    {
        throw new RuntimeException('Alias namespaces are not supported in TestManagerRegistry.');
    }

    public function getManagerNames(): array
    {
        return array_keys($this->managers);
    }

    public function getRepository($persistentObject, $persistentManagerName = null)
    {
        $manager = $this->getManager($persistentManagerName);

        if (!$manager instanceof EntityManagerInterface) {
            throw new RuntimeException('Repository requested for unknown manager.');
        }

        return $manager->getRepository($persistentObject);
    }

    public function getManagerForClass($class)
    {
        $managerName = $this->classMap[$class] ?? $this->defaultManagerName;

        return $this->managers[$managerName] ?? null;
    }

    public function mapClassToManager(string $class, string $managerName): void
    {
        $this->classMap[$class] = $managerName;
    }
}
