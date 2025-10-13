<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\StressApp;

use AlexFigures\Symfony\Tests\Fixtures\InMemory\InMemoryRepository;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Factory that creates InMemoryRepository with large dataset for stress testing.
 *
 * Since InMemoryRepository is final, we use reflection to inject the large dataset
 * after construction.
 */
final class StressRepositoryFactory
{
    public static function create(
        ResourceRegistryInterface $registry,
        ?PropertyAccessorInterface $accessor = null,
    ): InMemoryRepository {
        // Create standard InMemoryRepository
        $repository = new InMemoryRepository($registry, $accessor);

        // Create stress repository with large dataset
        $stressRepository = new StressInMemoryRepository($registry, $accessor);

        // Use reflection to replace data in InMemoryRepository
        $reflection = new \ReflectionClass(InMemoryRepository::class);
        $dataProp = $reflection->getProperty('data');
        $dataProp->setAccessible(true);

        // Get data from stress repository
        $stressReflection = new \ReflectionClass(StressInMemoryRepository::class);
        $stressDataProp = $stressReflection->getProperty('data');
        $stressDataProp->setAccessible(true);
        $stressData = $stressDataProp->getValue($stressRepository);

        // Inject large dataset into InMemoryRepository
        $dataProp->setValue($repository, $stressData);

        return $repository;
    }
}

