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
