<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Bridge\Symfony\DependencyInjection;

use JsonApi\Symfony\Resource\Attribute\JsonApiResource;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class JsonApiExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('jsonapi.strict_content_negotiation', $config['strict_content_negotiation']);
        $container->setParameter('jsonapi.media_type', $config['media_type']);
        $container->setParameter('jsonapi.route_prefix', $config['route_prefix']);
        $container->setParameter('jsonapi.pagination.default_size', $config['pagination']['default_size']);
        $container->setParameter('jsonapi.pagination.max_size', $config['pagination']['max_size']);
        $container->setParameter('jsonapi.sorting.whitelist', $config['sorting']['whitelist']);
        $container->setParameter('jsonapi.write.allow_relationship_writes', $config['write']['allow_relationship_writes']);
        $container->setParameter('jsonapi.write.client_generated_ids', $config['write']['client_generated_ids']);
        $container->setParameter('jsonapi.relationships.write_response', $config['relationships']['write_response']);
        $container->setParameter('jsonapi.relationships.linkage_in_resource', $config['relationships']['linkage_in_resource']);
        $container->setParameter('jsonapi.errors.expose_debug_meta', $config['errors']['expose_debug_meta']);
        $container->setParameter('jsonapi.errors.add_correlation_id', $config['errors']['add_correlation_id']);
        $container->setParameter('jsonapi.errors.default_title_map', $config['errors']['default_title_map']);
        $container->setParameter('jsonapi.errors.locale', $config['errors']['locale']);

        $this->registerAutoconfiguration($container);

        $configDirectory = __DIR__ . '/../../../../config';
        if (is_dir($configDirectory)) {
            $loader = new PhpFileLoader($container, new FileLocator($configDirectory));
            $loader->load('services.php');
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
    }
}
