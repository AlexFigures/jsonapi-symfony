<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Bridge\Symfony\Bundle;

use AlexFigures\Symfony\Bridge\Symfony\DependencyInjection\Compiler\CustomRouteHandlerPass;
use AlexFigures\Symfony\Bridge\Symfony\DependencyInjection\Compiler\ResourceDiscoveryPass;
use AlexFigures\Symfony\Bridge\Symfony\DependencyInjection\JsonApiExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * @psalm-suppress MissingConstructor
 */
final class JsonApiBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Register compiler pass for automatic resource discovery
        $container->addCompilerPass(new ResourceDiscoveryPass());

        // Register compiler pass for automatic handler registration (new in 0.3.0)
        $container->addCompilerPass(new CustomRouteHandlerPass());
    }

    public function getContainerExtension(): ExtensionInterface
    {
        if (!$this->extension instanceof ExtensionInterface) {
            $this->extension = new JsonApiExtension();
        }

        return $this->extension;
    }
}
