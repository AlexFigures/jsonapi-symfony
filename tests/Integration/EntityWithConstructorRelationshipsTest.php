<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Integration;

use JsonApi\Symfony\Contract\Data\ChangeSet;
use JsonApi\Symfony\Tests\Integration\Fixtures\Entity\Author;
use JsonApi\Symfony\Tests\Integration\Fixtures\Entity\EntityWithConstructor;
use JsonApi\Symfony\Tests\Integration\Fixtures\Entity\Tag;
use Symfony\Component\Uid\Uuid;

/**
 * Test that relationships are preserved during entity creation
 * when using SerializerEntityInstantiator (entities with non-empty constructors).
 * 
 * This addresses the critical issue where relationships were silently dropped
 * for entities that go through the instantiator path.
 */
final class EntityWithConstructorRelationshipsTest extends DoctrineIntegrationTestCase
{
    private Author $author;
    private Tag $tag1;
    private Tag $tag2;

    protected function getDatabaseUrl(): string
    {
        return $_ENV['DATABASE_URL_POSTGRES']
            ?? 'postgresql://jsonapi:secret@localhost:5432/jsonapi_test?serverVersion=16&charset=utf8';
    }

    protected function getPlatform(): string
    {
        return 'postgresql';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestData();
    }

    /**
     * Test that relationships are preserved when creating entities with constructors.
     * 
     * This is the critical test case from the review comment:
     * - Entity has non-empty constructor (goes through SerializerEntityInstantiator)
     * - POST request includes relationship linkage
     * - Relationships should NOT be silently ignored
     */
    public function testCreateEntityWithConstructorPreservesRelationships(): void
    {
        $changes = new ChangeSet(
            attributes: [
                'id' => Uuid::v4()->toString(),
                'name' => 'Test Entity',
                'status' => 'active',
            ],
            relationships: [
                'author' => ['data' => ['type' => 'authors', 'id' => $this->author->getId()]],
                'tags' => [
                    'data' => [
                        ['type' => 'tags', 'id' => $this->tag1->getId()],
                        ['type' => 'tags', 'id' => $this->tag2->getId()],
                    ]
                ],
            ]
        );

        // Create entity - this goes through SerializerEntityInstantiator due to constructor
        $entity = $this->validatingPersister->create('entities-with-constructor', $changes);

        // Verify entity was created correctly
        $this->assertInstanceOf(EntityWithConstructor::class, $entity);
        $this->assertSame('Test Entity', $entity->getName());
        $this->assertSame('active', $entity->getStatus());

        // CRITICAL: Verify relationships were NOT silently dropped
        $this->assertNotNull($entity->getAuthor(), 'Author relationship should be preserved');
        $this->assertSame($this->author->getId(), $entity->getAuthor()->getId());
        
        $this->assertCount(2, $entity->getTags(), 'Tags relationships should be preserved');
        $tagIds = array_map(fn($tag) => $tag->getId(), $entity->getTags()->toArray());
        $this->assertContains($this->tag1->getId(), $tagIds);
        $this->assertContains($this->tag2->getId(), $tagIds);
    }

    /**
     * Test that only relationships work (no attributes) for entities with constructors.
     */
    public function testCreateEntityWithConstructorOnlyRelationships(): void
    {
        $changes = new ChangeSet(
            attributes: [
                'id' => Uuid::v4()->toString(),
                'name' => 'Required Name', // Required by constructor
            ],
            relationships: [
                'author' => ['data' => ['type' => 'authors', 'id' => $this->author->getId()]],
            ]
        );

        $entity = $this->validatingPersister->create('entities-with-constructor', $changes);

        $this->assertInstanceOf(EntityWithConstructor::class, $entity);
        $this->assertNotNull($entity->getAuthor());
        $this->assertSame($this->author->getId(), $entity->getAuthor()->getId());
    }

    /**
     * Test that constructor parameters work correctly.
     */
    public function testEntityWithConstructorParameters(): void
    {
        $changes = new ChangeSet(
            attributes: [
                'id' => Uuid::v4()->toString(),
                'name' => 'Constructor Test',
                'status' => 'inactive', // Override default from constructor
            ]
        );

        $entity = $this->validatingPersister->create('entities-with-constructor', $changes);

        $this->assertInstanceOf(EntityWithConstructor::class, $entity);
        $this->assertSame('Constructor Test', $entity->getName());
        $this->assertSame('inactive', $entity->getStatus()); // Should override constructor default
    }

    private function createTestData(): void
    {
        // Create author
        $this->author = new Author();
        $this->author->setId(Uuid::v4()->toString());
        $this->author->setName('Test Author');
        $this->author->setEmail('author@example.com');
        $this->em->persist($this->author);

        // Create tags
        $this->tag1 = new Tag();
        $this->tag1->setId(Uuid::v4()->toString());
        $this->tag1->setName('Tag 1');
        $this->em->persist($this->tag1);

        $this->tag2 = new Tag();
        $this->tag2->setId(Uuid::v4()->toString());
        $this->tag2->setName('Tag 2');
        $this->em->persist($this->tag2);

        $this->em->flush();
    }
}
