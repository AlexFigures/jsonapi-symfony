<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Integration\Filter;

use JsonApi\Symfony\Filter\Operator\Registry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\Config\FileLocator;

/**
 * Integration test specifically for the "eq" operator that was failing
 * in the user's real project.
 */
final class EqualOperatorTest extends TestCase
{
    public function testEqualOperatorIsRegisteredAndWorking(): void
    {
        // Create a container and load the services configuration
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../../config'));
        $loader->load('services.php');

        // Make the Registry service public for testing
        $container->getDefinition(Registry::class)->setPublic(true);

        // Compile the container to process tagged services
        $container->compile();

        // Get the operator registry service
        $registry = $container->get(Registry::class);
        
        // Verify that the "eq" operator is registered
        $this->assertTrue($registry->has('eq'), 'The "eq" operator should be registered');
        
        // Get the operator and verify it works
        $eqOperator = $registry->get('eq');
        $this->assertSame('eq', $eqOperator->name());
        
        // Test the operator compilation (basic smoke test)
        $expression = $eqOperator->compile(
            rootAlias: 'e',
            dqlField: 'e.nameEn',
            values: ['United States'],
            platform: new \Doctrine\DBAL\Platforms\PostgreSQL120Platform()
        );
        
        // Verify the expression is generated correctly
        $this->assertStringContainsString('e.nameEn =', $expression->dql);
        $this->assertArrayHasKey(array_keys($expression->parameters)[0], $expression->parameters);
        $this->assertSame('United States', array_values($expression->parameters)[0]);
    }
    
    public function testEqualOperatorWithMultipleValues(): void
    {
        // Create a container and load the services configuration
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../../config'));
        $loader->load('services.php');

        // Make the Registry service public for testing
        $container->getDefinition(Registry::class)->setPublic(true);

        // Compile the container to process tagged services
        $container->compile();

        // Get the operator registry service
        $registry = $container->get(Registry::class);
        $eqOperator = $registry->get('eq');
        
        // Test with multiple values (should use first value)
        $expression = $eqOperator->compile(
            rootAlias: 'e',
            dqlField: 'e.isActive',
            values: [true, false], // Multiple values, should use first
            platform: new \Doctrine\DBAL\Platforms\PostgreSQL120Platform()
        );
        
        // Verify the expression uses the first value
        $this->assertStringContainsString('e.isActive =', $expression->dql);
        $this->assertTrue(array_values($expression->parameters)[0]);
    }
    
    public function testEqualOperatorThrowsExceptionWithEmptyValues(): void
    {
        // Create a container and load the services configuration
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../../config'));
        $loader->load('services.php');

        // Make the Registry service public for testing
        $container->getDefinition(Registry::class)->setPublic(true);

        // Compile the container to process tagged services
        $container->compile();

        // Get the operator registry service
        $registry = $container->get(Registry::class);
        $eqOperator = $registry->get('eq');
        
        // Test with empty values array
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('EqualOperator requires at least one value.');
        
        $eqOperator->compile(
            rootAlias: 'e',
            dqlField: 'e.nameEn',
            values: [], // Empty values should throw exception
            platform: new \Doctrine\DBAL\Platforms\PostgreSQL120Platform()
        );
    }
}
