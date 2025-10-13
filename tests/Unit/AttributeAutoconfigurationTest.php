<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit;

use AlexFigures\Symfony\Bridge\Symfony\DependencyInjection\JsonApiExtension;
use AlexFigures\Symfony\Contract\Data\ResourceProcessor;
use AlexFigures\Symfony\Resource\Attribute\JsonApiResource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(JsonApiExtension::class)]
final class AttributeAutoconfigurationTest extends TestCase
{
    public function testResourceAttributeRegistersTag(): void
    {
        $container = new ContainerBuilder();
        $extension = new JsonApiExtension();

        // Set required parameters for ResourceDiscoveryPass
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());

        $extension->load([], $container);

        // Test that autoconfiguration is registered
        // We can't easily test the actual autoconfiguration without compiling,
        // but we can verify that the extension registered it
        $reflection = new \ReflectionClass($container);
        $property = $reflection->getProperty('autoconfiguredAttributes');
        $property->setAccessible(true);
        $autoconfiguredAttributes = $property->getValue($container);

        // Check that JsonApiResource attribute is registered for autoconfiguration
        self::assertArrayHasKey(JsonApiResource::class, $autoconfiguredAttributes);
    }

    public function testDoctrineProviderAliasesResourceProcessor(): void
    {
        $container = new ContainerBuilder();
        $extension = new JsonApiExtension();

        // Set required parameters
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());

        // Load with default configuration (provider defaults to 'doctrine')
        $extension->load([], $container);

        // Verify that ResourceProcessor is aliased to ValidatingDoctrineProcessor
        self::assertTrue($container->hasAlias(ResourceProcessor::class));
        $alias = $container->getAlias(ResourceProcessor::class);
        self::assertSame(
            'AlexFigures\Symfony\Bridge\Doctrine\Persister\ValidatingDoctrineProcessor',
            (string) $alias
        );
    }

    public function testCustomProviderAliasesResourceProcessor(): void
    {
        $container = new ContainerBuilder();
        $extension = new JsonApiExtension();

        // Set required parameters
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());

        // Register a custom processor service
        $container->register('my_custom_processor', \stdClass::class);

        // Load with custom provider configuration
        $extension->load([
            [
                'data_layer' => [
                    'provider' => 'custom',
                    'processor' => 'my_custom_processor',
                ],
            ],
        ], $container);

        // Verify that ResourceProcessor is aliased to the custom processor
        self::assertTrue($container->hasAlias(ResourceProcessor::class));
        $alias = $container->getAlias(ResourceProcessor::class);
        self::assertSame('my_custom_processor', (string) $alias);
    }
}

#[JsonApiResource(type: 'articles')]
final class ResourceFixture
{
}
