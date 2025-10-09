<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Unit\Bridge;

use JsonApi\Symfony\Bridge\Symfony\DependencyInjection\Compiler\ResourceDiscoveryPass;
use JsonApi\Symfony\Resource\Attribute\JsonApiResource;
use JsonApi\Symfony\Resource\Registry\ResourceRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(ResourceDiscoveryPass::class)]
final class ResourceDiscoveryPassTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Create temporary directory for test fixtures
        $this->fixturesDir = sys_get_temp_dir() . '/jsonapi_test_' . uniqid();
        mkdir($this->fixturesDir, 0777, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Clean up temporary directory
        if (is_dir($this->fixturesDir)) {
            $this->removeDirectory($this->fixturesDir);
        }
    }

    public function testDiscoverResourcesInConfiguredDirectory(): void
    {
        // Create a test resource file
        $className = $this->createResourceFile('Product', 'products');

        $container = new ContainerBuilder();
        $container->setParameter('jsonapi.resource_paths', [$this->fixturesDir]);
        $container->setParameter('kernel.project_dir', dirname($this->fixturesDir));

        // Register ResourceRegistry service
        $container->register(ResourceRegistry::class)
            ->setArgument(0, []);

        $pass = new ResourceDiscoveryPass();
        $pass->process($container);

        // Check that resources were discovered
        $discoveredResources = $container->getParameter('jsonapi.discovered_resources');

        $this->assertIsArray($discoveredResources);
        $this->assertArrayHasKey('products', $discoveredResources);
        $this->assertSame($className, $discoveredResources['products']);
    }

    public function testDiscoverMultipleResources(): void
    {
        // Create multiple test resource files with unique names
        $productClass = $this->createResourceFile('ProductMulti', 'products');
        $categoryClass = $this->createResourceFile('CategoryMulti', 'categories');
        $tagClass = $this->createResourceFile('TagMulti', 'tags');

        $container = new ContainerBuilder();
        $container->setParameter('jsonapi.resource_paths', [$this->fixturesDir]);
        $container->setParameter('kernel.project_dir', dirname($this->fixturesDir));

        $container->register(ResourceRegistry::class)
            ->setArgument(0, []);

        $pass = new ResourceDiscoveryPass();
        $pass->process($container);

        $discoveredResources = $container->getParameter('jsonapi.discovered_resources');

        $this->assertCount(3, $discoveredResources);
        $this->assertSame($productClass, $discoveredResources['products']);
        $this->assertSame($categoryClass, $discoveredResources['categories']);
        $this->assertSame($tagClass, $discoveredResources['tags']);
    }

    public function testIgnoreClassesWithoutAttribute(): void
    {
        // Create a resource file WITH attribute
        $productClass = $this->createResourceFile('ProductIgnore', 'products');

        // Create a regular class WITHOUT attribute
        $this->createRegularFile('RegularClass');

        $container = new ContainerBuilder();
        $container->setParameter('jsonapi.resource_paths', [$this->fixturesDir]);
        $container->setParameter('kernel.project_dir', dirname($this->fixturesDir));

        $container->register(ResourceRegistry::class)
            ->setArgument(0, []);

        $pass = new ResourceDiscoveryPass();
        $pass->process($container);

        $discoveredResources = $container->getParameter('jsonapi.discovered_resources');

        // Only the resource with attribute should be discovered
        $this->assertCount(1, $discoveredResources);
        $this->assertSame($productClass, $discoveredResources['products']);
    }

    public function testSkipNonExistentDirectory(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('jsonapi.resource_paths', ['/non/existent/path']);
        $container->setParameter('kernel.project_dir', '/tmp');

        $container->register(ResourceRegistry::class)
            ->setArgument(0, []);

        $pass = new ResourceDiscoveryPass();
        $pass->process($container);

        $discoveredResources = $container->getParameter('jsonapi.discovered_resources');

        // Should return empty array for non-existent directory
        $this->assertIsArray($discoveredResources);
        $this->assertEmpty($discoveredResources);
    }

    public function testUpdateResourceRegistryDefinition(): void
    {
        $productClass = $this->createResourceFile('ProductUpdate', 'products');

        $container = new ContainerBuilder();
        $container->setParameter('jsonapi.resource_paths', [$this->fixturesDir]);
        $container->setParameter('kernel.project_dir', dirname($this->fixturesDir));

        // Register ResourceRegistry service
        $container->register(ResourceRegistry::class)
            ->setArgument(0, []); // Initially empty

        $pass = new ResourceDiscoveryPass();
        $pass->process($container);

        // Check that ResourceRegistry definition was updated
        $definition = $container->getDefinition(ResourceRegistry::class);
        $argument = $definition->getArgument(0);

        $this->assertIsArray($argument);
        $this->assertSame($productClass, $argument['products']);
    }

    /**
     * @return class-string
     */
    private function createResourceFile(string $className, string $resourceType): string
    {
        $namespace = 'JsonApi\\Symfony\\Tests\\Fixtures\\Discovery';
        $fullClassName = $namespace . '\\' . $className;

        $content = <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use JsonApi\Symfony\Resource\Attribute\JsonApiResource;
use JsonApi\Symfony\Resource\Attribute\Id;
use JsonApi\Symfony\Resource\Attribute\Attribute;

#[JsonApiResource(type: '{$resourceType}')]
final class {$className}
{
    #[Id]
    #[Attribute]
    public string \$id;

    #[Attribute]
    public string \$name;
}

PHP;

        $filePath = $this->fixturesDir . '/' . $className . '.php';
        file_put_contents($filePath, $content);

        // Include the file so the class is available
        require_once $filePath;

        return $fullClassName;
    }

    private function createRegularFile(string $className): void
    {
        $namespace = 'JsonApi\\Symfony\\Tests\\Fixtures\\Discovery';

        $content = <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

final class {$className}
{
    public string \$name;
}

PHP;

        file_put_contents($this->fixturesDir . '/' . $className . '.php', $content);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
