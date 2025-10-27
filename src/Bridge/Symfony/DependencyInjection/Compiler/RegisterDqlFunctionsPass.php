<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Bridge\Symfony\DependencyInjection\Compiler;

use AlexFigures\Symfony\Bridge\Doctrine\DQL\ILikeFunction;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Registers custom DQL functions with Doctrine ORM.
 *
 * This compiler pass automatically registers the ILIKE() DQL function
 * if Doctrine ORM is available and not already configured.
 *
 * Users can override this by configuring their own DQL functions in doctrine.yaml:
 * ```yaml
 * doctrine:
 *     orm:
 *         dql:
 *             string_functions:
 *                 ILIKE: App\Custom\ILikeFunction
 * ```
 */
final class RegisterDqlFunctionsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // Only register if Doctrine ORM is available
        if (!$container->hasParameter('doctrine.entity_managers')) {
            return;
        }

        // Get existing DQL configuration
        $dqlConfig = $container->hasParameter('doctrine.orm.configuration.dql')
            ? $container->getParameter('doctrine.orm.configuration.dql')
            : [];

        if (!is_array($dqlConfig)) {
            $dqlConfig = [];
        }

        // Initialize string_functions if not set
        if (!isset($dqlConfig['string_functions'])) {
            $dqlConfig['string_functions'] = [];
        }

        // Only register ILIKE if not already configured
        if (!isset($dqlConfig['string_functions']['ILIKE'])) {
            $dqlConfig['string_functions']['ILIKE'] = ILikeFunction::class;
        }

        // Update the parameter
        $container->setParameter('doctrine.orm.configuration.dql', $dqlConfig);

        // Also try to configure each entity manager directly
        $entityManagers = $container->getParameter('doctrine.entity_managers');
        if (!is_array($entityManagers)) {
            return;
        }

        foreach ($entityManagers as $name => $serviceId) {
            $configServiceId = sprintf('doctrine.orm.%s_configuration', $name);

            if (!$container->hasDefinition($configServiceId)) {
                continue;
            }

            $configDefinition = $container->getDefinition($configServiceId);

            // Add method call to register the DQL function
            $configDefinition->addMethodCall('addCustomStringFunction', ['ILIKE', ILikeFunction::class]);
        }
    }
}
