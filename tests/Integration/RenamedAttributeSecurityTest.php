<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Integration;

use JsonApi\Symfony\Bridge\Doctrine\Instantiator\SerializerEntityInstantiator;
use JsonApi\Symfony\Contract\Data\ChangeSet;
use JsonApi\Symfony\Resource\Attribute\SerializationGroups;
use JsonApi\Symfony\Resource\Metadata\AttributeMetadata;
use JsonApi\Symfony\Resource\Metadata\ResourceMetadata;
use PHPUnit\Framework\TestCase;

/**
 * Integration test demonstrating the security fix for renamed attributes.
 * 
 * This test simulates a real-world scenario where:
 * 1. An entity has a property `publishedAt` 
 * 2. It's renamed to `published-at` via #[Attribute(name: 'published-at')]
 * 3. It has #[SerializationGroups(['read'])] making it read-only
 * 4. A client tries to modify it via API
 * 
 * Before the fix: The attribute would be writable (security vulnerability)
 * After the fix: The attribute is correctly blocked
 */
final class RenamedAttributeSecurityTest extends TestCase
{
    public function testSecurityScenarioRenamedReadOnlyAttribute(): void
    {
        // Simulate a real entity with renamed read-only attribute
        $metadata = $this->createArticleMetadataWithRenamedReadOnlyPublishedAt();
        
        // Simulate a client trying to modify the read-only field
        $maliciousChanges = new ChangeSet(
            attributes: [
                'publishedAt' => '2024-01-01T00:00:00Z', // Client tries to set published date
                'title' => 'Legitimate Title Change'      // This should be allowed
            ]
        );

        // Create instantiator (this would normally be injected)
        $instantiator = $this->createSerializerEntityInstantiator();
        
        // Use reflection to access the private method for testing
        $reflection = new \ReflectionClass($instantiator);
        $filterMethod = $reflection->getMethod('filterBySerializationGroups');
        $filterMethod->setAccessible(true);

        // Filter the changes (this is what happens internally)
        $filteredChanges = $filterMethod->invoke($instantiator, $maliciousChanges, $metadata, false);

        // SECURITY ASSERTION: The read-only publishedAt should be blocked
        $this->assertArrayNotHasKey('publishedAt', $filteredChanges->attributes, 
            'Read-only renamed attribute should be blocked from writes');
        
        // The legitimate title change should be allowed
        $this->assertArrayHasKey('title', $filteredChanges->attributes,
            'Writable attributes should still be allowed');
        
        $this->assertSame('Legitimate Title Change', $filteredChanges->attributes['title']);
    }

    public function testSecurityScenarioRenamedWritableAttribute(): void
    {
        // Simulate an entity with renamed writable attribute
        $metadata = $this->createArticleMetadataWithRenamedWritableSlug();
        
        $changes = new ChangeSet(
            attributes: [
                'slug' => 'new-article-slug' // This should be allowed
            ]
        );

        $instantiator = $this->createSerializerEntityInstantiator();
        
        $reflection = new \ReflectionClass($instantiator);
        $filterMethod = $reflection->getMethod('filterBySerializationGroups');
        $filterMethod->setAccessible(true);

        $filteredChanges = $filterMethod->invoke($instantiator, $changes, $metadata, false);

        // Writable renamed attribute should be allowed
        $this->assertArrayHasKey('slug', $filteredChanges->attributes);
        $this->assertSame('new-article-slug', $filteredChanges->attributes['slug']);
    }

    private function createArticleMetadataWithRenamedReadOnlyPublishedAt(): ResourceMetadata
    {
        return new ResourceMetadata(
            type: 'articles',
            class: 'App\\Entity\\Article',
            attributes: [
                // This simulates: #[Attribute(name: 'published-at')] #[SerializationGroups(['read'])]
                'published-at' => new AttributeMetadata(
                    name: 'published-at',        // Renamed from publishedAt
                    propertyPath: 'publishedAt', // Original property path
                    serializationGroups: new SerializationGroups(['read']) // Read-only
                ),
                'title' => new AttributeMetadata(
                    name: 'title',
                    propertyPath: 'title',
                    serializationGroups: new SerializationGroups(['read', 'write']) // Read-write
                )
            ],
            relationships: []
        );
    }

    private function createArticleMetadataWithRenamedWritableSlug(): ResourceMetadata
    {
        return new ResourceMetadata(
            type: 'articles',
            class: 'App\\Entity\\Article',
            attributes: [
                // This simulates: #[Attribute(name: 'article-slug')] #[SerializationGroups(['read', 'write'])]
                'article-slug' => new AttributeMetadata(
                    name: 'article-slug',    // Renamed from slug
                    propertyPath: 'slug',    // Original property path
                    serializationGroups: new SerializationGroups(['read', 'write']) // Read-write
                )
            ],
            relationships: []
        );
    }

    private function createSerializerEntityInstantiator(): SerializerEntityInstantiator
    {
        $em = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $accessor = new \Symfony\Component\PropertyAccess\PropertyAccessor();
        
        return new SerializerEntityInstantiator($em, $accessor);
    }
}
