<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Unit\Resource\Registry;

use JsonApi\Symfony\Resource\Attribute\JsonApiResource;
use JsonApi\Symfony\Resource\Attribute\Relationship;
use JsonApi\Symfony\Resource\Registry\ResourceRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Tests that self-referential relationships (using "self" type hint) are properly resolved.
 *
 * @group unit
 */
final class SelfReferentialRelationshipTest extends TestCase
{
    public function testSelfReferentialRelationshipResolvesToActualClassName(): void
    {
        $registry = new ResourceRegistry([
            SelfReferentialEntity::class,
        ]);

        $metadata = $registry->getByType('self-referential-entities');

        $this->assertArrayHasKey('parent', $metadata->relationships);

        $parentRelationship = $metadata->relationships['parent'];

        // Verify that "self" was resolved to the actual class name
        $this->assertSame(SelfReferentialEntity::class, $parentRelationship->targetClass);

        // Verify that targetType is correctly set
        $this->assertSame('self-referential-entities', $parentRelationship->targetType);

        // Verify relationship properties
        $this->assertFalse($parentRelationship->toMany);
    }

}


#[JsonApiResource(type: 'self-referential-entities')]
class SelfReferentialEntity
{
    private string $id;

    #[Relationship(toMany: false, targetType: 'self-referential-entities')]
    private ?self $parent = null;

    public function getId(): string
    {
        return $this->id;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): void
    {
        $this->parent = $parent;
    }
}
