<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Unit;

use JsonApi\Symfony\Bridge\Symfony\DependencyInjection\JsonApiExtension;
use JsonApi\Symfony\Resource\Attribute\JsonApiResource;
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
}

#[JsonApiResource(type: 'articles')]
final class ResourceFixture
{
}
