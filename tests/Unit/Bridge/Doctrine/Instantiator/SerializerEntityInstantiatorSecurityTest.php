<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Unit\Bridge\Doctrine\Instantiator;

use Doctrine\ORM\EntityManagerInterface;
use JsonApi\Symfony\Bridge\Doctrine\Instantiator\SerializerEntityInstantiator;
use JsonApi\Symfony\Contract\Data\ChangeSet;
use JsonApi\Symfony\Resource\Attribute\SerializationGroups;
use JsonApi\Symfony\Resource\Metadata\AttributeMetadata;
use JsonApi\Symfony\Resource\Metadata\ResourceMetadata;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccessor;

/**
 * Test for security issue: renamed attributes with serialization groups.
 * 
 * When an attribute is renamed via #[Attribute(name: 'published-at')] and has
 * #[SerializationGroups(['read'])], the metadata is keyed by 'published-at'
 * but ChangeSet uses the property path 'publishedAt'. The lookup must work
 * correctly to prevent read-only fields from being writable.
 */
final class SerializerEntityInstantiatorSecurityTest extends TestCase
{
    private SerializerEntityInstantiator $instantiator;

    protected function setUp(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $accessor = new PropertyAccessor();
        
        $this->instantiator = new SerializerEntityInstantiator($em, $accessor);
    }

    public function testRenamedAttributeWithReadOnlySerializationGroupsIsRespected(): void
    {
        // Create metadata with a renamed attribute that has read-only serialization groups
        $readOnlyGroups = new SerializationGroups(['read']); // Only readable, not writable
        
        $metadata = new ResourceMetadata(
            type: 'articles',
            class: 'App\\Entity\\Article',
            attributes: [
                // Key is the renamed attribute name, but propertyPath is the original
                'published-at' => new AttributeMetadata(
                    name: 'published-at',           // Renamed via #[Attribute(name: 'published-at')]
                    propertyPath: 'publishedAt',    // Original property path
                    serializationGroups: $readOnlyGroups
                ),
                'title' => new AttributeMetadata(
                    name: 'title',
                    propertyPath: 'title'
                )
            ],
            relationships: []
        );

        // ChangeSet uses property paths, not renamed attribute names
        $changes = new ChangeSet([
            'publishedAt' => '2024-01-01T00:00:00Z', // This should be blocked (read-only)
            'title' => 'Test Article'                // This should be allowed
        ]);

        // Use reflection to test the private filterBySerializationGroups method
        $reflection = new \ReflectionClass($this->instantiator);
        $method = $reflection->getMethod('filterBySerializationGroups');
        $method->setAccessible(true);

        $filteredChanges = $method->invoke($this->instantiator, $changes, $metadata, true);

        // The read-only 'publishedAt' should be filtered out
        $this->assertArrayNotHasKey('publishedAt', $filteredChanges->attributes);
        
        // The writable 'title' should remain
        $this->assertArrayHasKey('title', $filteredChanges->attributes);
        $this->assertSame('Test Article', $filteredChanges->attributes['title']);
    }

    public function testRenamedAttributeWithWritableSerializationGroupsIsAllowed(): void
    {
        // Create metadata with a renamed attribute that has writable serialization groups
        $writableGroups = new SerializationGroups(['read', 'write']);
        
        $metadata = new ResourceMetadata(
            type: 'articles',
            class: 'App\\Entity\\Article',
            attributes: [
                'published-at' => new AttributeMetadata(
                    name: 'published-at',
                    propertyPath: 'publishedAt',
                    serializationGroups: $writableGroups
                )
            ],
            relationships: []
        );

        $changes = new ChangeSet([
            'publishedAt' => '2024-01-01T00:00:00Z'
        ]);

        // Use reflection to test the private filterBySerializationGroups method
        $reflection = new \ReflectionClass($this->instantiator);
        $method = $reflection->getMethod('filterBySerializationGroups');
        $method->setAccessible(true);

        $filteredChanges = $method->invoke($this->instantiator, $changes, $metadata, true);

        // The writable 'publishedAt' should be allowed
        $this->assertArrayHasKey('publishedAt', $filteredChanges->attributes);
        $this->assertSame('2024-01-01T00:00:00Z', $filteredChanges->attributes['publishedAt']);
    }

    public function testAttributeWithoutSerializationGroupsIsAllowedByDefault(): void
    {
        $metadata = new ResourceMetadata(
            type: 'articles',
            class: 'App\\Entity\\Article',
            attributes: [
                'title' => new AttributeMetadata(
                    name: 'title',
                    propertyPath: 'title'
                    // No serialization groups = allowed by default
                )
            ],
            relationships: []
        );

        $changes = new ChangeSet([
            'title' => 'Test Article'
        ]);

        // Use reflection to test the private filterBySerializationGroups method
        $reflection = new \ReflectionClass($this->instantiator);
        $method = $reflection->getMethod('filterBySerializationGroups');
        $method->setAccessible(true);

        $filteredChanges = $method->invoke($this->instantiator, $changes, $metadata, true);

        // Attributes without serialization groups should be allowed
        $this->assertArrayHasKey('title', $filteredChanges->attributes);
        $this->assertSame('Test Article', $filteredChanges->attributes['title']);
    }

    public function testUnknownAttributeIsAllowedByDefault(): void
    {
        $metadata = new ResourceMetadata(
            type: 'articles',
            class: 'App\\Entity\\Article',
            attributes: [],
            relationships: []
        );

        $changes = new ChangeSet([
            'unknownField' => 'some value'
        ]);

        // Use reflection to test the private filterBySerializationGroups method
        $reflection = new \ReflectionClass($this->instantiator);
        $method = $reflection->getMethod('filterBySerializationGroups');
        $method->setAccessible(true);

        $filteredChanges = $method->invoke($this->instantiator, $changes, $metadata, true);

        // Unknown attributes should be allowed by default (for backward compatibility)
        $this->assertArrayHasKey('unknownField', $filteredChanges->attributes);
        $this->assertSame('some value', $filteredChanges->attributes['unknownField']);
    }

    public function testPrepareDataForDenormalizationWithRenamedAttribute(): void
    {
        // Test that prepareDataForDenormalization also uses the correct lookup method
        $metadata = new ResourceMetadata(
            type: 'articles',
            class: 'App\\Entity\\Article',
            attributes: [
                'published-at' => new AttributeMetadata(
                    name: 'published-at',
                    propertyPath: 'publishedAt'
                )
            ],
            relationships: []
        );

        $changes = new ChangeSet([
            'publishedAt' => '2024-01-01T00:00:00Z' // Property path, not renamed attribute name
        ]);

        // Use reflection to test the private prepareDataForDenormalization method
        $reflection = new \ReflectionClass($this->instantiator);
        $method = $reflection->getMethod('prepareDataForDenormalization');
        $method->setAccessible(true);

        $data = $method->invoke($this->instantiator, $changes, $metadata);

        // Should correctly map to the property path
        $this->assertArrayHasKey('publishedAt', $data);
        $this->assertSame('2024-01-01T00:00:00Z', $data['publishedAt']);
    }
}
