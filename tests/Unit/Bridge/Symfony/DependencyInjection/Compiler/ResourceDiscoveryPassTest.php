<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Unit\Bridge\Symfony\DependencyInjection\Compiler;

use JsonApi\Symfony\Bridge\Symfony\DependencyInjection\Compiler\ResourceDiscoveryPass;
use JsonApi\Symfony\Resource\Attribute\JsonApiResource;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class ResourceDiscoveryPassTest extends TestCase
{
    public function testProcessWithoutResourcePaths(): void
    {
        $container = new ContainerBuilder();
        $pass = new ResourceDiscoveryPass();

        // Should not throw any exceptions
        $pass->process($container);

        // Should not set any parameters
        $this->assertFalse($container->hasParameter('jsonapi.discovered_resources'));
        $this->assertFalse($container->hasParameter('jsonapi.discovered_custom_routes'));
    }

    public function testProcessWithResourcePaths(): void
    {
        $container = new ContainerBuilder();
        $testPath = realpath(__DIR__ . '/../../../../../Fixtures/Model');

        $container->setParameter('jsonapi.resource_paths', [
            $testPath
        ]);

        // Add the ResourceRegistry definition
        $registryDefinition = new Definition();
        $registryDefinition->setArguments([[]]);
        $container->setDefinition('JsonApi\Symfony\Resource\Registry\ResourceRegistry', $registryDefinition);

        // Add the CustomRouteRegistry definition
        $customRouteRegistryDefinition = new Definition();
        $customRouteRegistryDefinition->setArguments([[]]);
        $container->setDefinition('JsonApi\Symfony\Resource\Registry\CustomRouteRegistry', $customRouteRegistryDefinition);

        $pass = new ResourceDiscoveryPass();
        $pass->process($container);

        // Should set discovered resources parameter
        $this->assertTrue($container->hasParameter('jsonapi.discovered_resources'));
        $discoveredResources = $container->getParameter('jsonapi.discovered_resources');
        $this->assertIsArray($discoveredResources);

        // Should set discovered custom routes parameter
        $this->assertTrue($container->hasParameter('jsonapi.discovered_custom_routes'));
        $discoveredCustomRoutes = $container->getParameter('jsonapi.discovered_custom_routes');
        $this->assertIsArray($discoveredCustomRoutes);

        // Should find our test fixtures
        $this->assertArrayHasKey('custom-articles', $discoveredResources);
        $this->assertStringContainsString('ArticleWithCustomRoutes', $discoveredResources['custom-articles']);

        // Should find custom routes from both entity and controller
        $this->assertNotEmpty($discoveredCustomRoutes);

        // Routes are now stored as arrays (serializable format)
        $routeNames = array_map(fn ($route) => $route['name'], $discoveredCustomRoutes);
        $this->assertContains('articles.publish', $routeNames);
        $this->assertContains('articles.archive', $routeNames);
        $this->assertContains('articles.search', $routeNames);
        $this->assertContains('articles.trending', $routeNames);
    }

    public function testProcessUpdatesRegistryDefinitions(): void
    {
        $container = new ContainerBuilder();
        $testPath = realpath(__DIR__ . '/../../../../../Fixtures/Model');

        $container->setParameter('jsonapi.resource_paths', [
            $testPath
        ]);

        // Add the ResourceRegistry definition
        $registryDefinition = new Definition();
        $registryDefinition->setArguments([[]]);
        $container->setDefinition('JsonApi\Symfony\Resource\Registry\ResourceRegistry', $registryDefinition);

        // Add the CustomRouteRegistry definition
        $customRouteRegistryDefinition = new Definition();
        $customRouteRegistryDefinition->setArguments([[]]);
        $container->setDefinition('JsonApi\Symfony\Resource\Registry\CustomRouteRegistry', $customRouteRegistryDefinition);

        $pass = new ResourceDiscoveryPass();
        $pass->process($container);

        // Check that ResourceRegistry definition was updated
        $updatedRegistryDefinition = $container->getDefinition('JsonApi\Symfony\Resource\Registry\ResourceRegistry');
        $this->assertNotEmpty($updatedRegistryDefinition->getArgument(0));

        // Check that CustomRouteRegistry definition was updated
        $updatedCustomRouteRegistryDefinition = $container->getDefinition('JsonApi\Symfony\Resource\Registry\CustomRouteRegistry');
        $this->assertNotEmpty($updatedCustomRouteRegistryDefinition->getArgument(0));
    }

    public function testIsControllerClassDetectsControllerByName(): void
    {
        $pass = new ResourceDiscoveryPass();
        $reflection = new \ReflectionClass(TestSearchController::class);

        // Use reflection to access the private method
        $method = new \ReflectionMethod($pass, 'isControllerClass');
        $method->setAccessible(true);

        $result = $method->invoke($pass, $reflection);
        $this->assertTrue($result, 'Class with "Controller" in name should be detected as controller');
    }

    public function testIsControllerClassDetectsEntityByAttribute(): void
    {
        $pass = new ResourceDiscoveryPass();
        $reflection = new \ReflectionClass(TestEntity::class);

        // Use reflection to access the private method
        $method = new \ReflectionMethod($pass, 'isControllerClass');
        $method->setAccessible(true);

        $result = $method->invoke($pass, $reflection);
        $this->assertFalse($result, 'Class with JsonApiResource attribute should not be detected as controller');
    }

    public function testIsControllerClassDetectsControllerByPublicMethods(): void
    {
        $pass = new ResourceDiscoveryPass();
        $reflection = new \ReflectionClass(TestServiceWithPublicMethods::class);

        // Use reflection to access the private method
        $method = new \ReflectionMethod($pass, 'isControllerClass');
        $method->setAccessible(true);

        $result = $method->invoke($pass, $reflection);
        $this->assertTrue($result, 'Class with public methods should be detected as controller');
    }
}

// Test fixtures
class TestSearchController
{
    public function search(): string
    {
        return 'search';
    }
}

#[JsonApiResource(type: 'test')]
class TestEntity
{
    public $id;
}

class TestServiceWithPublicMethods
{
    public function doSomething(): void
    {
    }
}
