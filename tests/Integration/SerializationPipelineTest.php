<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Integration;

use JsonApi\Symfony\Bridge\Doctrine\Instantiator\SerializerEntityInstantiator;
use JsonApi\Symfony\Bridge\Doctrine\Persister\ValidatingDoctrinePersister;
use JsonApi\Symfony\Contract\Data\ChangeSet;
use JsonApi\Symfony\Http\Exception\ValidationException;
use JsonApi\Symfony\Http\Validation\ConstraintViolationMapper;
use JsonApi\Symfony\Http\Validation\DatabaseErrorMapper;
use JsonApi\Symfony\Resource\Metadata\OperationGroups;
use JsonApi\Symfony\Resource\Metadata\ResourceMetadata;
use JsonApi\Symfony\Tests\Integration\Fixtures\Entity\Product;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;

/**
 * Integration test demonstrating the complete "Deserialize → Validate → Write" pipeline.
 * 
 * Tests the enhanced serialization approach with:
 * - Strict denormalization with error aggregation
 * - Operation-specific validation and serialization groups
 * - Database error mapping and transaction handling
 * - JSON:API error formatting with proper pointers
 */
final class SerializationPipelineTest extends DoctrineIntegrationTestCase
{
    protected function getDatabaseUrl(): string
    {
        return $_ENV['DATABASE_URL_POSTGRES']
            ?? 'postgresql://jsonapi:secret@localhost:5432/jsonapi_test?serverVersion=16&charset=utf8';
    }

    protected function getPlatform(): string
    {
        return 'postgresql';
    }

    public function testCreateWithValidData(): void
    {
        $metadata = $this->registry->getByType('products');
        
        $changes = new ChangeSet(
            attributes: [
                'name' => 'Test Product',
                'price' => 99.99,
            ]
        );

        $entity = $this->validatingPersister->create('products', $changes);

        $this->assertInstanceOf(Product::class, $entity);
        $this->assertSame('Test Product', $entity->getName());
        $this->assertSame(99.99, $entity->getPrice());
        $this->assertNotNull($entity->getId());
    }

    public function testCreateWithValidationErrors(): void
    {
        $changes = new ChangeSet(
            attributes: [
                'name' => '', // Invalid: empty name
                'price' => -10, // Invalid: negative price
            ]
        );

        $this->expectException(ValidationException::class);
        
        try {
            $this->validatingPersister->create('products', $changes);
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertCount(2, $errors);
            
            // Check that errors have proper JSON:API pointers
            $pointers = array_map(fn($error) => $error->source?->pointer, $errors);
            $this->assertContains('/data/attributes/name', $pointers);
            $this->assertContains('/data/attributes/price', $pointers);
            
            throw $e;
        }
    }

    public function testCreateWithDenormalizationErrors(): void
    {
        $changes = new ChangeSet(
            attributes: [
                'name' => 'Valid Product',
                'price' => 'invalid-price', // Type error
                'unknown_field' => 'value', // Extra attribute
            ]
        );

        $this->expectException(ValidationException::class);
        
        try {
            $this->validatingPersister->create('products', $changes);
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertGreaterThan(0, count($errors));
            
            // Should contain denormalization errors
            $this->assertTrue(
                str_contains($e->getMessage(), 'denormalization') ||
                str_contains($e->getMessage(), 'normalization') ||
                str_contains($e->getMessage(), 'validation')
            );
            
            throw $e;
        }
    }

    public function testUpdateWithValidationGroups(): void
    {
        // First create a product
        $changes = new ChangeSet(
            attributes: [
                'name' => 'Original Product',
                'price' => 50.0,
            ]
        );

        $entity = $this->validatingPersister->create('products', $changes);
        $this->em->clear(); // Clear to test update

        // Now update it
        $updateChanges = new ChangeSet(
            attributes: [
                'name' => 'Updated Product',
                'price' => 75.0,
            ]
        );

        $updatedEntity = $this->validatingPersister->update('products', (string) $entity->getId(), $updateChanges);

        $this->assertSame('Updated Product', $updatedEntity->getName());
        $this->assertSame(75.0, $updatedEntity->getPrice());
    }

    public function testOperationGroupsConfiguration(): void
    {
        $metadata = $this->registry->getByType('products');
        $operationGroups = $metadata->getOperationGroups();

        // Test default groups
        $this->assertSame(['create', 'Default'], $operationGroups->getValidationGroups(true));
        $this->assertSame(['update', 'Default'], $operationGroups->getValidationGroups(false));
        $this->assertSame(['write', 'create'], $operationGroups->getSerializationGroups(true));
        $this->assertSame(['write', 'update'], $operationGroups->getSerializationGroups(false));
    }

    public function testCustomOperationGroups(): void
    {
        $customGroups = new OperationGroups(
            validationGroupsCreate: ['strict_create'],
            validationGroupsUpdate: ['strict_update'],
            serializationGroupsCreate: ['api_create'],
            serializationGroupsUpdate: ['api_update']
        );

        $this->assertSame(['strict_create'], $customGroups->getValidationGroups(true));
        $this->assertSame(['strict_update'], $customGroups->getValidationGroups(false));
        $this->assertSame(['api_create'], $customGroups->getSerializationGroups(true));
        $this->assertSame(['api_update'], $customGroups->getSerializationGroups(false));
    }

    public function testSerializerEntityInstantiatorIntegration(): void
    {
        $instantiator = new SerializerEntityInstantiator($this->em, $this->registry, $this->accessor);
        
        // Test that serializer and denormalizer are available
        $this->assertNotNull($instantiator->serializer());
        $this->assertNotNull($instantiator->denormalizer());
        
        // Test data preparation
        $changes = new ChangeSet(
            attributes: ['name' => 'Test', 'price' => 100.0]
        );
        
        $metadata = $this->registry->getByType('products');
        $data = $instantiator->prepareDataForDenormalization($changes, $metadata);
        
        $this->assertIsArray($data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('price', $data);
    }

    public function testErrorMappingIntegration(): void
    {
        $violationMapper = new ConstraintViolationMapper($this->violationMapper->errors);
        $databaseErrorMapper = new DatabaseErrorMapper($this->registry, $this->violationMapper->errors);
        
        // Test that mappers are properly configured
        $this->assertInstanceOf(ConstraintViolationMapper::class, $violationMapper);
        $this->assertInstanceOf(DatabaseErrorMapper::class, $databaseErrorMapper);
        
        // Test unknown exception handling
        $unknownException = new \RuntimeException('Unknown error');
        $result = $databaseErrorMapper->mapDatabaseError('products', $unknownException);
        $this->assertSame($unknownException, $result);
    }

    public function testTransactionWrapping(): void
    {
        $changes = new ChangeSet(
            attributes: [
                'name' => 'Transactional Product',
                'price' => 25.0,
            ]
        );

        // This should be wrapped in a transaction
        $entity = $this->validatingPersister->create('products', $changes);
        
        $this->assertNotNull($entity->getId());
        
        // Verify entity was persisted
        $this->em->clear();
        $found = $this->em->find(Product::class, $entity->getId());
        $this->assertNotNull($found);
        $this->assertSame('Transactional Product', $found->getName());
    }

    public function testDeleteOperation(): void
    {
        // Create a product first
        $changes = new ChangeSet(
            attributes: [
                'name' => 'Product to Delete',
                'price' => 10.0,
            ]
        );

        $entity = $this->validatingPersister->create('products', $changes);
        $id = $entity->getId();
        
        // Delete it
        $this->validatingPersister->delete('products', (string) $id);
        
        // Verify it's gone
        $this->em->clear();
        $found = $this->em->find(Product::class, $id);
        $this->assertNull($found);
    }
}
