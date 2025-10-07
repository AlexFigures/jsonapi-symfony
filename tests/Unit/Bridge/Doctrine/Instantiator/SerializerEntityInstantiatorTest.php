<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Unit\Bridge\Doctrine\Instantiator;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use JsonApi\Symfony\Bridge\Doctrine\Instantiator\SerializerEntityInstantiator;
use JsonApi\Symfony\Contract\Data\ChangeSet;
use JsonApi\Symfony\Resource\Attribute\SerializationGroups;
use JsonApi\Symfony\Resource\Metadata\AttributeMetadata;
use JsonApi\Symfony\Resource\Metadata\ResourceMetadata;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Uid\Uuid;

final class SerializerEntityInstantiatorTest extends TestCase
{
    private EntityManagerInterface $em;
    private PropertyAccessor $accessor;
    private SerializerEntityInstantiator $instantiator;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->accessor = new PropertyAccessor();
        $this->instantiator = new SerializerEntityInstantiator($this->em, $this->accessor);
    }

    public function testInstantiateWithoutConstructor(): void
    {
        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->method('newInstance')->willReturn(new SimpleEntity());

        $this->em->method('getClassMetadata')->willReturn($classMetadata);

        $metadata = new ResourceMetadata(
            type: 'simple',
            class: SimpleEntity::class,
            attributes: [],
            relationships: [],
        );

        $changes = new ChangeSet(['name' => 'Test']);

        $result = $this->instantiator->instantiate(SimpleEntity::class, $metadata, $changes);

        $this->assertInstanceOf(SimpleEntity::class, $result['entity']);
        $this->assertEquals($changes, $result['remainingChanges']);
    }

    public function testInstantiateWithConstructorParameters(): void
    {
        $metadata = new ResourceMetadata(
            type: 'entities-with-constructor',
            class: EntityWithConstructor::class,
            attributes: [
                'name' => new AttributeMetadata('name', 'name'),
                'email' => new AttributeMetadata('email', 'email'),
            ],
            relationships: [],
        );

        $changes = new ChangeSet([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $result = $this->instantiator->instantiate(EntityWithConstructor::class, $metadata, $changes, true);

        /** @var EntityWithConstructor $entity */
        $entity = $result['entity'];

        $this->assertInstanceOf(EntityWithConstructor::class, $entity);
        $this->assertEquals('John Doe', $entity->name);
        $this->assertEquals('john@example.com', $entity->email);
        $this->assertInstanceOf(Uuid::class, $entity->uuid);
    }

    public function testSerializationGroupsCreate(): void
    {
        $metadata = new ResourceMetadata(
            type: 'entities-with-groups',
            class: EntityWithSerializationGroups::class,
            attributes: [
                'name' => new AttributeMetadata(
                    'name',
                    'name',
                    serializationGroups: new SerializationGroups(['read', 'write'])
                ),
                'slug' => new AttributeMetadata(
                    'slug',
                    'slug',
                    serializationGroups: new SerializationGroups(['read', 'create'])
                ),
                'updatedAt' => new AttributeMetadata(
                    'updatedAt',
                    'updatedAt',
                    serializationGroups: new SerializationGroups(['read', 'update'])
                ),
            ],
            relationships: [],
        );

        $changes = new ChangeSet([
            'name' => 'Test Entity',
            'slug' => 'test-entity',
            'updatedAt' => '2025-01-01',
        ]);

        // During creation (isCreate = true)
        $result = $this->instantiator->instantiate(
            EntityWithSerializationGroups::class,
            $metadata,
            $changes,
            isCreate: true
        );

        /** @var EntityWithSerializationGroups $entity */
        $entity = $result['entity'];

        // name and slug should be set (write and create)
        $this->assertEquals('Test Entity', $entity->name);
        $this->assertEquals('test-entity', $entity->slug);
        
        // updatedAt should NOT be set (only update, not create)
        $this->assertNull($entity->updatedAt);
    }

    public function testSerializationGroupsUpdate(): void
    {
        $metadata = new ResourceMetadata(
            type: 'entities-with-groups',
            class: EntityWithSerializationGroups::class,
            attributes: [
                'name' => new AttributeMetadata(
                    'name',
                    'name',
                    serializationGroups: new SerializationGroups(['read', 'write'])
                ),
                'slug' => new AttributeMetadata(
                    'slug',
                    'slug',
                    serializationGroups: new SerializationGroups(['read', 'create'])
                ),
                'updatedAt' => new AttributeMetadata(
                    'updatedAt',
                    'updatedAt',
                    serializationGroups: new SerializationGroups(['read', 'update'])
                ),
            ],
            relationships: [],
        );

        $changes = new ChangeSet([
            'name' => 'Updated Entity',
            'slug' => 'should-not-change',
            'updatedAt' => '2025-01-02',
        ]);

        // During update (isCreate = false)
        $result = $this->instantiator->instantiate(
            EntityWithSerializationGroups::class,
            $metadata,
            $changes,
            isCreate: false
        );

        /** @var EntityWithSerializationGroups $entity */
        $entity = $result['entity'];

        // name and updatedAt should be set (write and update)
        $this->assertEquals('Updated Entity', $entity->name);
        $this->assertEquals('2025-01-02', $entity->updatedAt);
        
        // slug should NOT be set (only create, not update)
        $this->assertNull($entity->slug);
    }
}

// Test fixtures

class SimpleEntity
{
    public ?string $name = null;
}

class EntityWithConstructor
{
    public Uuid $uuid;
    public string $name;
    public string $email;

    public function __construct(string $name, string $email)
    {
        $this->uuid = Uuid::v7();
        $this->name = $name;
        $this->email = $email;
    }
}

class EntityWithSerializationGroups
{
    public Uuid $uuid;
    public ?string $name = null;
    public ?string $slug = null;
    public ?string $updatedAt = null;

    public function __construct(?string $name = null, ?string $slug = null, ?string $updatedAt = null)
    {
        $this->uuid = Uuid::v7();
        $this->name = $name;
        $this->slug = $slug;
        $this->updatedAt = $updatedAt;
    }
}

