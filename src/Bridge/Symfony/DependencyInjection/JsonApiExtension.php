<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Bridge\Symfony\DependencyInjection;

use JsonApi\Symfony\Profile\ProfileInterface;
use JsonApi\Symfony\Resource\Attribute\JsonApiResource;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

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

        $container->setParameter('jsonapi.strict_content_negotiation', $config['strict_content_negotiation']);
        $container->setParameter('jsonapi.media_type', $config['media_type']);
        $container->setParameter('jsonapi.route_prefix', $config['route_prefix']);
        $container->setParameter('jsonapi.routing', $config['routing']);
        $container->setParameter('jsonapi.routing.naming_convention', $config['routing']['naming_convention']);
        $container->setParameter('jsonapi.pagination.default_size', $config['pagination']['default_size']);
        $container->setParameter('jsonapi.pagination.max_size', $config['pagination']['max_size']);
        $container->setParameter('jsonapi.write.allow_relationship_writes', $config['write']['allow_relationship_writes']);
        $container->setParameter('jsonapi.write.client_generated_ids', $config['write']['client_generated_ids']);
        $container->setParameter('jsonapi.relationships.write_response', $config['relationships']['write_response']);
        $container->setParameter('jsonapi.relationships.linkage_in_resource', $config['relationships']['linkage_in_resource']);
        $container->setParameter('jsonapi.errors.expose_debug_meta', $config['errors']['expose_debug_meta']);
        $container->setParameter('jsonapi.errors.add_correlation_id', $config['errors']['add_correlation_id']);
        $container->setParameter('jsonapi.errors.default_title_map', $config['errors']['default_title_map']);
        $container->setParameter('jsonapi.errors.locale', $config['errors']['locale']);
        $container->setParameter('jsonapi.cache', $config['cache']);
        $container->setParameter('jsonapi.limits', $config['limits']);
        $container->setParameter('jsonapi.performance', $config['performance']);
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
        $container->setParameter('jsonapi.dx', $config['dx']);
        $container->setParameter('jsonapi.docs.generator', $config['docs']['generator']);
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

        // Configure data layer aliases AFTER loading services.php
        // This will override the default Null implementations
        $this->configureDataLayer($container, $config['data_layer']);
    }

    private function registerAutoconfiguration(ContainerBuilder $container): void
    {
        $container->registerAttributeForAutoconfiguration(
            JsonApiResource::class,
            static function (ChildDefinition $definition, JsonApiResource $attribute): void {
                $definition->addTag('jsonapi.resource', ['type' => $attribute->type]);
            }
        );

        $container->registerForAutoconfiguration(ProfileInterface::class)
            ->addTag('jsonapi.profile');
    }

    /**
     * Configure data layer service aliases based on configuration.
     *
     * @param array{provider: string, repository: string|null, persister: string|null, relationship_reader: string|null, transaction_manager: string|null} $config
     */
    private function configureDataLayer(ContainerBuilder $container, array $config): void
    {
        if ($config['provider'] === 'doctrine') {
            // Use Doctrine implementations
            $container->setAlias(
                'JsonApi\Symfony\Contract\Data\ResourceRepository',
                'JsonApi\Symfony\Bridge\Doctrine\Repository\GenericDoctrineRepository'
            )->setPublic(false);

            $container->setAlias(
                'JsonApi\Symfony\Contract\Data\ResourcePersister',
                'JsonApi\Symfony\Bridge\Doctrine\Persister\ValidatingDoctrinePersister'
            )->setPublic(false);

            $container->setAlias(
                'JsonApi\Symfony\Contract\Data\RelationshipReader',
                'JsonApi\Symfony\Bridge\Doctrine\Relationship\GenericDoctrineRelationshipHandler'
            )->setPublic(false);

            $container->setAlias(
                'JsonApi\Symfony\Contract\Tx\TransactionManager',
                'JsonApi\Symfony\Bridge\Doctrine\Transaction\DoctrineTransactionManager'
            )->setPublic(false);
        } elseif ($config['provider'] === 'custom') {
            // Use custom implementations
            if ($config['repository'] !== null) {
                $container->setAlias(
                    'JsonApi\Symfony\Contract\Data\ResourceRepository',
                    $config['repository']
                )->setPublic(false);
            }

            if ($config['persister'] !== null) {
                $container->setAlias(
                    'JsonApi\Symfony\Contract\Data\ResourcePersister',
                    $config['persister']
                )->setPublic(false);
            }

            if ($config['relationship_reader'] !== null) {
                $container->setAlias(
                    'JsonApi\Symfony\Contract\Data\RelationshipReader',
                    $config['relationship_reader']
                )->setPublic(false);
            }

            if ($config['transaction_manager'] !== null) {
                $container->setAlias(
                    'JsonApi\Symfony\Contract\Tx\TransactionManager',
                    $config['transaction_manager']
                )->setPublic(false);
            }
        }
    }
}
