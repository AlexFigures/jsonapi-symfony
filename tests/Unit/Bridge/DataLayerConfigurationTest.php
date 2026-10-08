<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Unit\Bridge;

use AlexFigures\JsonApi\Bridge\Symfony\DependencyInjection\JsonApiExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(JsonApiExtension::class)]
final class DataLayerConfigurationTest extends TestCase
{
    public function testAcceptanceServicesShareConfiguredPolicies(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        (new JsonApiExtension())->load([['atomic' => ['enabled' => true], 'limits' => ['filter_max_depth' => 4]]], $container);
        $options = $container->getDefinition(\AlexFigures\JsonApi\Http\Controller\OptionsController::class);
        self::assertTrue($options->hasTag('controller.service_arguments'));
        self::assertSame('%jsonapi.atomic.enabled%', $container->getDefinition(\AlexFigures\JsonApi\Bridge\Symfony\EventSubscriber\ContentNegotiationSubscriber::class)->getArgument(2));
        self::assertSame(4, $container->getParameter('jsonapi.filter_max_depth'));
        self::assertSame(\AlexFigures\JsonApi\Bridge\Doctrine\Concurrency\DoctrineWriteConcurrencyGuard::class, (string) $container->getAlias(\AlexFigures\JsonApi\Contract\Data\WriteConcurrencyGuardInterface::class));
        self::assertSame(\AlexFigures\JsonApi\Resource\Registry\ResourceRegistryInterface::class, (string) $container->getDefinition(\AlexFigures\JsonApi\Atomic\Execution\AtomicTransaction::class)->getArgument(1));
        self::assertSame(\AlexFigures\JsonApi\Bridge\Doctrine\Identifier\DoctrineIdentifierMetadataValidator::class, (string) $container->getDefinition(\AlexFigures\JsonApi\Bridge\Symfony\Routing\JsonApiRouteLoader::class)->getArgument(6));
        foreach ([\AlexFigures\JsonApi\Atomic\Execution\Handlers\AddHandler::class => 6, \AlexFigures\JsonApi\Atomic\Execution\Handlers\UpdateHandler::class => 5] as $handler => $index) {
            self::assertSame(\AlexFigures\JsonApi\Http\Write\InputDocumentValidator::class, (string) $container->getDefinition($handler)->getArgument($index));
        }
    }

    public function testDoctrineRepresentationPreloaderIsOptionalAndWiredToDocumentBuilder(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        (new JsonApiExtension())->load([], $container);
        $capability = \AlexFigures\JsonApi\Contract\Data\RepresentationPreloaderInterface::class;
        self::assertSame(\AlexFigures\JsonApi\Bridge\Doctrine\Read\DoctrineRepresentationPreloader::class, (string) $container->getAlias($capability));
        self::assertSame($capability, (string) $container->getDefinition(\AlexFigures\JsonApi\Http\Document\DocumentBuilder::class)->getArgument(5));
        self::assertSame('legacy', $container->getParameter('jsonapi.performance.doctrine.collection_sort_policy'));
    }

    public function testRemovedConfigurationParametersAreNotPublished(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        (new JsonApiExtension())->load([], $container);
        self::assertFalse($container->hasParameter('jsonapi.dx'));
        self::assertFalse($container->hasParameter('jsonapi.errors.locale'));
        self::assertSame(['doctrine' => ['collection_sort_policy' => 'legacy'], 'head_enabled' => true], $container->getParameter('jsonapi.performance'));
    }

    public function testDefaultDoctrineProvider(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());

        $extension = new JsonApiExtension();
        $extension->load([], $container);

        // Don't compile - just check that aliases are created
        // Check that Doctrine aliases are created
        $this->assertTrue($container->hasAlias(\AlexFigures\JsonApi\Contract\Data\ResourceRepository::class));
        $this->assertTrue($container->hasAlias(\AlexFigures\JsonApi\Contract\Data\ResourceProcessor::class));
        $this->assertTrue($container->hasAlias(\AlexFigures\JsonApi\Contract\Data\RelationshipReader::class));
        $this->assertTrue($container->hasAlias(\AlexFigures\JsonApi\Contract\Tx\TransactionManager::class));

        // Check that repository alias points to ResourceRepositoryLocator (which uses GenericDoctrineRepository as fallback)
        $repositoryAlias = $container->getAlias(\AlexFigures\JsonApi\Contract\Data\ResourceRepository::class);
        $this->assertSame(
            \AlexFigures\JsonApi\Bridge\Symfony\Locator\ResourceRepositoryLocator::class,
            (string) $repositoryAlias
        );

        $processorAlias = $container->getAlias(\AlexFigures\JsonApi\Contract\Data\ResourceProcessor::class);
        $this->assertSame(
            \AlexFigures\JsonApi\Bridge\Symfony\Locator\ResourceProcessorLocator::class,
            (string) $processorAlias
        );

        $relationshipAlias = $container->getAlias(\AlexFigures\JsonApi\Contract\Data\RelationshipReader::class);
        $this->assertSame(
            \AlexFigures\JsonApi\Bridge\Symfony\Locator\RelationshipReaderLocator::class,
            (string) $relationshipAlias
        );

        self::assertSame(\AlexFigures\JsonApi\Bridge\Doctrine\Relationship\GenericDoctrineRelationshipHandler::class, (string) $container->getDefinition(\AlexFigures\JsonApi\Bridge\Symfony\Locator\RelationshipReaderLocator::class)->getArgument(1));
        self::assertSame(\AlexFigures\JsonApi\Bridge\Doctrine\Relationship\GenericDoctrineRelationshipHandler::class, (string) $container->getDefinition(\AlexFigures\JsonApi\Bridge\Symfony\Locator\RelationshipUpdaterLocator::class)->getArgument(1));

        $transactionAlias = $container->getAlias(\AlexFigures\JsonApi\Contract\Tx\TransactionManager::class);
        $this->assertSame(
            \AlexFigures\JsonApi\Bridge\Doctrine\Transaction\DoctrineTransactionManager::class,
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
        $this->assertTrue($container->hasAlias(\AlexFigures\JsonApi\Contract\Data\ResourceRepository::class));

        $repositoryAlias = $container->getAlias(\AlexFigures\JsonApi\Contract\Data\ResourceRepository::class);
        $this->assertSame(
            \AlexFigures\JsonApi\Bridge\Symfony\Locator\ResourceRepositoryLocator::class,
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
                    'processor' => 'App\Custom\Processor',
                    'relationship_reader' => 'App\Custom\RelationshipReader',
                    'transaction_manager' => 'App\Custom\TransactionManager',
                ],
            ],
        ], $container);

        // Don't compile - just check that aliases are created
        // Check that custom aliases are created
        $this->assertTrue($container->hasAlias(\AlexFigures\JsonApi\Contract\Data\ResourceRepository::class));
        $this->assertTrue($container->hasAlias(\AlexFigures\JsonApi\Contract\Data\ResourceProcessor::class));
        $this->assertTrue($container->hasAlias(\AlexFigures\JsonApi\Contract\Data\RelationshipReader::class));
        $this->assertTrue($container->hasAlias(\AlexFigures\JsonApi\Contract\Tx\TransactionManager::class));

        // Check that aliases point to custom implementations
        $repositoryAlias = $container->getAlias(\AlexFigures\JsonApi\Contract\Data\ResourceRepository::class);
        $this->assertSame('App\Custom\Repository', (string) $repositoryAlias);

        $processorAlias = $container->getAlias(\AlexFigures\JsonApi\Contract\Data\ResourceProcessor::class);
        $this->assertSame(\AlexFigures\JsonApi\Bridge\Symfony\Locator\ResourceProcessorLocator::class, (string) $processorAlias);
        self::assertSame('App\Custom\Processor', (string) $container->getDefinition(\AlexFigures\JsonApi\Bridge\Symfony\Locator\ResourceProcessorLocator::class)->getArgument(1));

        $relationshipAlias = $container->getAlias(\AlexFigures\JsonApi\Contract\Data\RelationshipReader::class);
        $this->assertSame(\AlexFigures\JsonApi\Bridge\Symfony\Locator\RelationshipReaderLocator::class, (string) $relationshipAlias);
        self::assertSame('App\Custom\RelationshipReader', (string) $container->getDefinition(\AlexFigures\JsonApi\Bridge\Symfony\Locator\RelationshipReaderLocator::class)->getArgument(1));

        $transactionAlias = $container->getAlias(\AlexFigures\JsonApi\Contract\Tx\TransactionManager::class);
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
        $this->assertTrue($container->hasAlias(\AlexFigures\JsonApi\Contract\Data\ResourceRepository::class));

        $repositoryAlias = $container->getAlias(\AlexFigures\JsonApi\Contract\Data\ResourceRepository::class);
        $this->assertSame('App\Custom\Repository', (string) $repositoryAlias);

        // Other aliases should still exist from services.php (Null implementations)
        $this->assertTrue($container->hasAlias(\AlexFigures\JsonApi\Contract\Data\ResourceProcessor::class));
        $this->assertTrue($container->hasAlias(\AlexFigures\JsonApi\Contract\Data\RelationshipReader::class));
        $this->assertTrue($container->hasAlias(\AlexFigures\JsonApi\Contract\Tx\TransactionManager::class));

        // They should point to Null implementations (not overridden)
        $processorAlias = $container->getAlias(\AlexFigures\JsonApi\Contract\Data\ResourceProcessor::class);
        $this->assertSame(\AlexFigures\JsonApi\Bridge\Symfony\Locator\ResourceProcessorLocator::class, (string) $processorAlias);
        self::assertSame('jsonapi.null_resource_processor', (string) $container->getDefinition(\AlexFigures\JsonApi\Bridge\Symfony\Locator\ResourceProcessorLocator::class)->getArgument(1));
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
        $repositoryAlias = $container->getAlias(\AlexFigures\JsonApi\Contract\Data\ResourceRepository::class);
        $this->assertFalse($repositoryAlias->isPublic());

        $processorAlias = $container->getAlias(\AlexFigures\JsonApi\Contract\Data\ResourceProcessor::class);
        $this->assertFalse($processorAlias->isPublic());

        $relationshipAlias = $container->getAlias(\AlexFigures\JsonApi\Contract\Data\RelationshipReader::class);
        $this->assertFalse($relationshipAlias->isPublic());

        $transactionAlias = $container->getAlias(\AlexFigures\JsonApi\Contract\Tx\TransactionManager::class);
        $this->assertFalse($transactionAlias->isPublic());
    }
}
