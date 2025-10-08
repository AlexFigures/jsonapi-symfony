<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Integration\Filter;

use JsonApi\Symfony\Filter\Operator\Registry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\Config\FileLocator;

/**
 * Integration test to verify that filter operators are properly registered
 * when using the service configuration.
 */
final class OperatorRegistrationTest extends TestCase
{
    public function testOperatorsAreRegisteredFromServiceConfiguration(): void
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
        $this->assertInstanceOf(Registry::class, $registry);
        
        // Verify that all expected operators are registered
        $expectedOperators = [
            'eq',      // EqualOperator
            'neq',     // NotEqualOperator
            'lt',      // LessThanOperator
            'lte',     // LessOrEqualOperator
            'gt',      // GreaterThanOperator
            'gte',     // GreaterOrEqualOperator
            'like',    // LikeOperator
            'in',      // InOperator
            'nin',     // NotInOperator
            'isnull',  // IsNullOperator
            'between', // BetweenOperator
        ];
        
        foreach ($expectedOperators as $operatorName) {
            $this->assertTrue(
                $registry->has($operatorName),
                sprintf('Operator "%s" should be registered', $operatorName)
            );
            
            // Verify we can get the operator without exception
            $operator = $registry->get($operatorName);
            $this->assertSame($operatorName, $operator->name());
        }
        
        // Verify the total count matches expected
        $allOperators = $registry->all();
        $this->assertCount(count($expectedOperators), $allOperators);
    }
    
    public function testRegistryThrowsExceptionForUnknownOperator(): void
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
        
        // Verify that unknown operators throw an exception
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown operator "unknown".');
        
        $registry->get('unknown');
    }
}
