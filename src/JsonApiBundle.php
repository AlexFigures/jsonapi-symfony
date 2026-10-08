<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi;

use AlexFigures\JsonApi\Bridge\Symfony\DependencyInjection\Compiler\CustomRouteHandlerPass;
use AlexFigures\JsonApi\Bridge\Symfony\DependencyInjection\Compiler\RegisterDqlFunctionsPass;
use AlexFigures\JsonApi\Bridge\Symfony\DependencyInjection\Compiler\ResourceDiscoveryPass;
use AlexFigures\JsonApi\Bridge\Symfony\DependencyInjection\Compiler\ValidateProfilesPass;
use AlexFigures\JsonApi\Bridge\Symfony\DependencyInjection\JsonApiExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * @psalm-suppress MissingConstructor
 * @api
 */
final class JsonApiBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Register compiler pass for automatic resource discovery
        $container->addCompilerPass(new ResourceDiscoveryPass());

        // Register compiler pass for automatic handler registration
        $container->addCompilerPass(new CustomRouteHandlerPass());

        // Register compiler pass for profile validation (runs after resource discovery)
        $container->addCompilerPass(new ValidateProfilesPass());

        // Register compiler pass for DQL functions (ILIKE, etc.)
        $container->addCompilerPass(new RegisterDqlFunctionsPass());
    }

    public function getContainerExtension(): ExtensionInterface
    {
        if (!$this->extension instanceof ExtensionInterface) {
            $this->extension = new JsonApiExtension();
        }

        return $this->extension;
    }
}
