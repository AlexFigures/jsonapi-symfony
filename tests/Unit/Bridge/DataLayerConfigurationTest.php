<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Bridge;

use AlexFigures\Symfony\Bridge\Symfony\DependencyInjection\JsonApiExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(JsonApiExtension::class)]
final class DataLayerConfigurationTest extends TestCase
{
    public function testDefaultDoctrineProvider(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());

        $extension = new JsonApiExtension();
        $extension->load([], $container);

        // Don't compile - just check that aliases are created
        // Check that Doctrine aliases are created
        $this->assertTrue($container->hasAlias('AlexFigures\Symfony\Contract\Data\ResourceRepository'));
        $this->assertTrue($container->hasAlias('AlexFigures\Symfony\Contract\Data\ResourcePersister'));
        $this->assertTrue($container->hasAlias('AlexFigures\Symfony\Contract\Data\RelationshipReader'));
        $this->assertTrue($container->hasAlias('AlexFigures\Symfony\Contract\Tx\TransactionManager'));

        // Check that aliases point to Doctrine implementations
        $repositoryAlias = $container->getAlias('AlexFigures\Symfony\Contract\Data\ResourceRepository');
        $this->assertSame(
            'AlexFigures\Symfony\Bridge\Doctrine\Repository\GenericDoctrineRepository',
            (string) $repositoryAlias
        );

        $persisterAlias = $container->getAlias('AlexFigures\Symfony\Contract\Data\ResourcePersister');
        $this->assertSame(
            'AlexFigures\Symfony\Bridge\Doctrine\Persister\ValidatingDoctrinePersister',
            (string) $persisterAlias
        );

        $relationshipAlias = $container->getAlias('AlexFigures\Symfony\Contract\Data\RelationshipReader');
        $this->assertSame(
            'AlexFigures\Symfony\Bridge\Doctrine\Relationship\GenericDoctrineRelationshipHandler',
            (string) $relationshipAlias
        );

        $transactionAlias = $container->getAlias('AlexFigures\Symfony\Contract\Tx\TransactionManager');
        $this->assertSame(
            'AlexFigures\Symfony\Bridge\Doctrine\Transaction\DoctrineTransactionManager',
            (string) $transactionAlias
        );
    }

    public function testExplicitDoctrineProvider(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());

        $extension = new JsonApiExtension();
        $extension->load([
            [
                'data_layer' => [
                    'provider' => 'doctrine',
                ],
            ],
        ], $container);

        // Don't compile - just check that aliases are created
        // Check that Doctrine aliases are created
        $this->assertTrue($container->hasAlias('AlexFigures\Symfony\Contract\Data\ResourceRepository'));

        $repositoryAlias = $container->getAlias('AlexFigures\Symfony\Contract\Data\ResourceRepository');
        $this->assertSame(
            'AlexFigures\Symfony\Bridge\Doctrine\Repository\GenericDoctrineRepository',
            (string) $repositoryAlias
        );
    }

    public function testCustomProvider(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());

        $extension = new JsonApiExtension();
        $extension->load([
            [
                'data_layer' => [
                    'provider' => 'custom',
                    'repository' => 'App\Custom\Repository',
                    'persister' => 'App\Custom\Persister',
                    'relationship_reader' => 'App\Custom\RelationshipReader',
                    'transaction_manager' => 'App\Custom\TransactionManager',
                ],
            ],
        ], $container);

        // Don't compile - just check that aliases are created
        // Check that custom aliases are created
        $this->assertTrue($container->hasAlias('AlexFigures\Symfony\Contract\Data\ResourceRepository'));
        $this->assertTrue($container->hasAlias('AlexFigures\Symfony\Contract\Data\ResourcePersister'));
        $this->assertTrue($container->hasAlias('AlexFigures\Symfony\Contract\Data\RelationshipReader'));
        $this->assertTrue($container->hasAlias('AlexFigures\Symfony\Contract\Tx\TransactionManager'));

        // Check that aliases point to custom implementations
        $repositoryAlias = $container->getAlias('AlexFigures\Symfony\Contract\Data\ResourceRepository');
        $this->assertSame('App\Custom\Repository', (string) $repositoryAlias);

        $persisterAlias = $container->getAlias('AlexFigures\Symfony\Contract\Data\ResourcePersister');
        $this->assertSame('App\Custom\Persister', (string) $persisterAlias);

        $relationshipAlias = $container->getAlias('AlexFigures\Symfony\Contract\Data\RelationshipReader');
        $this->assertSame('App\Custom\RelationshipReader', (string) $relationshipAlias);

        $transactionAlias = $container->getAlias('AlexFigures\Symfony\Contract\Tx\TransactionManager');
        $this->assertSame('App\Custom\TransactionManager', (string) $transactionAlias);
    }

    public function testPartialCustomProvider(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());

        $extension = new JsonApiExtension();
        $extension->load([
            [
                'data_layer' => [
                    'provider' => 'custom',
                    'repository' => 'App\Custom\Repository',
                    // Other services are null - should not override default aliases from services.php
                ],
            ],
        ], $container);

        // Don't compile - just check that aliases are created
        // Check that repository alias is overridden
        $this->assertTrue($container->hasAlias('AlexFigures\Symfony\Contract\Data\ResourceRepository'));

        $repositoryAlias = $container->getAlias('AlexFigures\Symfony\Contract\Data\ResourceRepository');
        $this->assertSame('App\Custom\Repository', (string) $repositoryAlias);

        // Other aliases should still exist from services.php (Null implementations)
        $this->assertTrue($container->hasAlias('AlexFigures\Symfony\Contract\Data\ResourcePersister'));
        $this->assertTrue($container->hasAlias('AlexFigures\Symfony\Contract\Data\RelationshipReader'));
        $this->assertTrue($container->hasAlias('AlexFigures\Symfony\Contract\Tx\TransactionManager'));

        // They should point to Null implementations (not overridden)
        $persisterAlias = $container->getAlias('AlexFigures\Symfony\Contract\Data\ResourcePersister');
        $this->assertSame('jsonapi.null_resource_persister', (string) $persisterAlias);
    }

    public function testDataLayerParameterIsStored(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());

        $extension = new JsonApiExtension();
        $extension->load([
            [
                'data_layer' => [
                    'provider' => 'custom',
                    'repository' => 'App\Custom\Repository',
                ],
            ],
        ], $container);

        // Check that data_layer parameter is stored
        $this->assertTrue($container->hasParameter('jsonapi.data_layer'));

        $dataLayerConfig = $container->getParameter('jsonapi.data_layer');
        $this->assertIsArray($dataLayerConfig);
        $this->assertSame('custom', $dataLayerConfig['provider']);
        $this->assertSame('App\Custom\Repository', $dataLayerConfig['repository']);
    }

    public function testAliasesAreNotPublic(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());

        $extension = new JsonApiExtension();
        $extension->load([], $container);

        // Don't compile - just check that aliases are not public
        // Check that aliases are not public
        $repositoryAlias = $container->getAlias('AlexFigures\Symfony\Contract\Data\ResourceRepository');
        $this->assertFalse($repositoryAlias->isPublic());

        $persisterAlias = $container->getAlias('AlexFigures\Symfony\Contract\Data\ResourcePersister');
        $this->assertFalse($persisterAlias->isPublic());

        $relationshipAlias = $container->getAlias('AlexFigures\Symfony\Contract\Data\RelationshipReader');
        $this->assertFalse($relationshipAlias->isPublic());

        $transactionAlias = $container->getAlias('AlexFigures\Symfony\Contract\Tx\TransactionManager');
        $this->assertFalse($transactionAlias->isPublic());
    }
}
