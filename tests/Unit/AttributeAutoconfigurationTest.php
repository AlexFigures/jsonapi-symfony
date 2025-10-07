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

        $container->register(ResourceFixture::class)
            ->setAutoconfigured(true)
            ->setAutowired(true)
            ->setPublic(true)
        ;

        $container->compile();

        $definition = $container->getDefinition(ResourceFixture::class);
        self::assertTrue($definition->hasTag('jsonapi.resource'));

        $tags = $definition->getTag('jsonapi.resource');
        self::assertSame('articles', $tags[0]['type']);
    }
}

#[JsonApiResource(type: 'articles')]
final class ResourceFixture
{
}
