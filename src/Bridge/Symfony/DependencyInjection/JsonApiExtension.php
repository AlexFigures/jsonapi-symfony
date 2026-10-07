<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Bridge\Symfony\DependencyInjection;

use AlexFigures\Symfony\Profile\ProfileInterface;
use AlexFigures\Symfony\Resource\Attribute\JsonApiResource;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

use function trigger_deprecation;

final class JsonApiExtension extends Extension
{
    public function getAlias(): string
    {
        return 'jsonapi';
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        if (!$container->hasParameter('jsonapi.discovered_resources')) {
            $container->setParameter('jsonapi.discovered_resources', []);
        }
        $container->setParameter('jsonapi.relationships.unplanned_read_policy', $config['relationships']['unplanned_read_policy']);

        $container->setParameter('jsonapi.strict_content_negotiation', $config['strict_content_negotiation']);

        $mediaTypes = $config['media_types'];
        if ($config['media_type'] !== null) {
            trigger_deprecation('alexfigures/symfony-jsonapi', '0.4.0', 'Configuring "jsonapi.media_type" is deprecated. Use the "jsonapi.media_types" section instead.');

            $legacy = $config['media_type'];
            $mediaTypes['default']['request']['allowed'] = [$legacy];
            $mediaTypes['default']['response']['default'] = $legacy;
            $mediaTypes['default']['response']['negotiable'] = [$legacy];
        }

        $container->setParameter('jsonapi.media_types', $mediaTypes);
        $container->setParameter('jsonapi.media_type', $mediaTypes['default']['response']['default']);
        $container->setParameter('jsonapi.route_prefix', $config['route_prefix']);
        $container->setParameter('jsonapi.pagination.default_size', $config['pagination']['default_size']);
        $container->setParameter('jsonapi.pagination.max_size', $config['pagination']['max_size']);
        $container->setParameter('jsonapi.write.allow_relationship_writes', $config['write']['allow_relationship_writes']);
        $container->setParameter('jsonapi.write.client_generated_ids', $config['write']['client_generated_ids']);
        $container->setParameter('jsonapi.relationships.write_response', $config['relationships']['write_response']);
        $container->setParameter('jsonapi.relationships.linkage_in_resource', $config['relationships']['linkage_in_resource']);
        $container->setParameter('jsonapi.errors.expose_debug_meta', $config['errors']['expose_debug_meta']);
        $container->setParameter('jsonapi.errors.add_correlation_id', $config['errors']['add_correlation_id']);
        $container->setParameter('jsonapi.errors.default_title_map', $config['errors']['default_title_map']);
        $container->setParameter('jsonapi.cache', $config['cache']);
        $container->setParameter('jsonapi.limits', $config['limits']);
        $container->setParameter('jsonapi.relationship_max_identifiers', $config['limits']['relationship_max_identifiers']);
        $container->setParameter('jsonapi.filter_max_depth', $config['limits']['filter_max_depth']);
        $container->setParameter('jsonapi.performance', $config['performance']);
        $container->setParameter('jsonapi.performance.head_enabled', $config['performance']['head_enabled']);
        $container->setParameter('jsonapi.performance.doctrine.collection_sort_policy', $config['performance']['doctrine']['collection_sort_policy']);
        $container->setParameter('jsonapi.atomic.enabled', $config['atomic']['enabled']);
        $container->setParameter('jsonapi.atomic.endpoint', $config['atomic']['endpoint']);
        $container->setParameter('jsonapi.atomic.require_ext_header', $config['atomic']['require_ext_header']);
        $container->setParameter('jsonapi.atomic.max_operations', $config['atomic']['max_operations']);
        $container->setParameter('jsonapi.atomic.return_policy', $config['atomic']['return_policy']);
        $container->setParameter('jsonapi.atomic.allow_href', $config['atomic']['allow_href']);
        $container->setParameter('jsonapi.atomic.lid.accept_in_resource_and_identifier', $config['atomic']['lid']['accept_in_resource_and_identifier']);
        $container->setParameter('jsonapi.profiles.negotiation', $config['profiles']['negotiation']);
        $container->setParameter('jsonapi.profiles.enabled_by_default', $config['profiles']['enabled_by_default']);
        $container->setParameter('jsonapi.profiles.per_type', $config['profiles']['per_type']);
        $container->setParameter('jsonapi.profiles.soft_delete', $config['profiles']['soft_delete']);
        $container->setParameter('jsonapi.profiles.audit_trail', $config['profiles']['audit_trail']);
        $container->setParameter('jsonapi.profiles.rel_counts', $config['profiles']['rel_counts']);
        $container->setParameter('jsonapi.docs.generator', $config['docs']['generator']);
        $container->setParameter('jsonapi.docs.generator.json_schema', $config['docs']['generator']['json_schema']);
        $container->setParameter('jsonapi.docs.generator.openapi', $config['docs']['generator']['openapi']);
        $container->setParameter('jsonapi.docs.ui', $config['docs']['ui']);
        $container->setParameter('jsonapi.release', $config['release']);

        // Store resource paths for ResourceDiscoveryPass
        $container->setParameter('jsonapi.resource_paths', $config['resource_paths']);

        // Store data layer configuration
        $container->setParameter('jsonapi.data_layer', $config['data_layer']);

        $this->registerAutoconfiguration($container);

        $configDirectory = __DIR__ . '/../../../../config';
        if (is_dir($configDirectory)) {
            $loader = new PhpFileLoader($container, new FileLocator($configDirectory));
            $loader->load('services.php');

            // Load Custom Route Handler services (new in 0.3.0)
            $loader->load('services_custom_routes.php');

            // Conditional loading of Atomic Operations
            if ($config['atomic']['enabled']) {
                $loader->load('services_atomic.php');
            }
        }

        if ($config['cache']['etag']['strategy'] === 'version') {
            $container->setAlias(\AlexFigures\Symfony\Http\Cache\EtagGeneratorInterface::class, \AlexFigures\Symfony\Http\Cache\VersionEtagGenerator::class);
        }

        // Configure data layer aliases AFTER loading services.php
        // This will override the default Null implementations
        $this->configureDataLayer($container, $config['data_layer']);
        // Keep configured/native providers as fallbacks; typed extensions override only supported types.
        foreach ([
            \AlexFigures\Symfony\Contract\Data\RelationshipReader::class => \AlexFigures\Symfony\Bridge\Symfony\Locator\RelationshipReaderLocator::class,
            \AlexFigures\Symfony\Contract\Data\RelationshipUpdater::class => \AlexFigures\Symfony\Bridge\Symfony\Locator\RelationshipUpdaterLocator::class,
        ] as $contract => $locator) {
            $fallback = (string) $container->getAlias($contract);
            $container->getDefinition($locator)->setArgument(1, new \Symfony\Component\DependencyInjection\Reference($fallback));
            $container->setAlias($contract, $locator);
        }

    }

    private function registerAutoconfiguration(ContainerBuilder $container): void
    {
        $container->registerAttributeForAutoconfiguration(
            JsonApiResource::class,
            static function (ChildDefinition $definition, JsonApiResource $attribute): void {
                $definition->addTag('jsonapi.resource', ['type' => $attribute->type]);
            }
        );

        $container->registerForAutoconfiguration(\AlexFigures\Symfony\Contract\Data\RelationshipBatchReaderInterface::class)->addTag('jsonapi.relationship_batch_reader');

        $container->registerForAutoconfiguration(\AlexFigures\Symfony\Contract\Data\TypedResourcePersister::class)->addTag('jsonapi.persister');
        $container->registerForAutoconfiguration(\AlexFigures\Symfony\Contract\Data\TypedRelationshipReader::class)->addTag('jsonapi.relationship_reader');
        $container->registerForAutoconfiguration(\AlexFigures\Symfony\Contract\Data\TypedRelationshipUpdater::class)->addTag('jsonapi.relationship_updater');

        $container->registerForAutoconfiguration(ProfileInterface::class)
            ->addTag('jsonapi.profile');
    }

    /**
     * Configure data layer service aliases based on configuration.
     *
     * @param array{provider: string, repository: string|null, processor: string|null, relationship_reader: string|null, transaction_manager: string|null} $config
     */
    private function configureDataLayer(ContainerBuilder $container, array $config): void
    {
        if ($config['provider'] === 'custom') {
            // Doctrine serializer/flush hooks must not enlist the ORM in a custom provider.
            $container->removeDefinition(\AlexFigures\Symfony\Bridge\Symfony\EventListener\WriteListener::class);
            $container->removeDefinition(\AlexFigures\Symfony\Bridge\Serializer\Normalizer\JsonApiRelationshipDenormalizer::class);
        }
        if ($config['provider'] === 'doctrine') {
            $container->setAlias(\AlexFigures\Symfony\Contract\Data\RepresentationPreloaderInterface::class, \AlexFigures\Symfony\Bridge\Doctrine\Read\DoctrineRepresentationPreloader::class);
            $container->setAlias(\AlexFigures\Symfony\Contract\Data\WriteConcurrencyGuardInterface::class, \AlexFigures\Symfony\Bridge\Doctrine\Concurrency\DoctrineWriteConcurrencyGuard::class);
            $container->getDefinition('AlexFigures\\Symfony\\Bridge\\Symfony\\Routing\\JsonApiRouteLoader')
                ->setArgument(6, new \Symfony\Component\DependencyInjection\Reference('AlexFigures\\Symfony\\Bridge\\Doctrine\\Identifier\\DoctrineIdentifierMetadataValidator'));

            // Use ResourceRepositoryLocator to support both custom TypedResourceRepository
            // and fallback to GenericDoctrineRepository for Doctrine entities
            $container->setAlias(
                'AlexFigures\Symfony\Contract\Data\ResourceRepository',
                'AlexFigures\Symfony\Bridge\Symfony\Locator\ResourceRepositoryLocator'
            )->setPublic(false);

            $container->setAlias(
                'AlexFigures\Symfony\Contract\Data\ResourceProcessor',
                \AlexFigures\Symfony\Bridge\Symfony\Locator\ResourceProcessorLocator::class
            )->setPublic(false);

            $container->setAlias(
                'AlexFigures\Symfony\Contract\Data\RelationshipReader',
                'AlexFigures\Symfony\Bridge\Doctrine\Relationship\GenericDoctrineRelationshipHandler'
            )->setPublic(false);

            $container->setAlias(
                'AlexFigures\Symfony\Contract\Data\RelationshipUpdater',
                'AlexFigures\Symfony\Bridge\Doctrine\Relationship\GenericDoctrineRelationshipHandler'
            )->setPublic(false);

            $container->setAlias(
                'AlexFigures\Symfony\Contract\Tx\TransactionManager',
                'AlexFigures\Symfony\Bridge\Doctrine\Transaction\DoctrineTransactionManager'
            )->setPublic(false);

            $container->setAlias(
                'AlexFigures\Symfony\Contract\Data\ExistenceChecker',
                'AlexFigures\Symfony\Bridge\Doctrine\ExistenceChecker\DoctrineExistenceChecker'
            )->setPublic(false);
        } elseif ($config['provider'] === 'custom') {
            $container->getDefinition(\AlexFigures\Symfony\Bridge\Symfony\Locator\ResourceProcessorLocator::class)->setArgument(1, new \Symfony\Component\DependencyInjection\Reference($config['processor'] ?? 'jsonapi.null_resource_processor'));
            $container->setAlias(\AlexFigures\Symfony\Contract\Data\ResourceProcessor::class, \AlexFigures\Symfony\Bridge\Symfony\Locator\ResourceProcessorLocator::class);
            // Use custom implementations
            if ($config['repository'] !== null) {
                $container->setAlias(
                    'AlexFigures\Symfony\Contract\Data\ResourceRepository',
                    $config['repository']
                )->setPublic(false);
            }



            if ($config['relationship_reader'] !== null) {
                $container->setAlias(
                    'AlexFigures\Symfony\Contract\Data\RelationshipReader',
                    $config['relationship_reader']
                )->setPublic(false);
            }

            if ($config['transaction_manager'] !== null) {
                $container->setAlias(
                    'AlexFigures\Symfony\Contract\Tx\TransactionManager',
                    $config['transaction_manager']
                )->setPublic(false);
            }
        }
    }
}
