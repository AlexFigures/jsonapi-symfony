<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Unit\Bridge\Doctrine\Instantiator;

use AlexFigures\JsonApi\Bridge\Doctrine\Instantiator\SerializerEntityInstantiator;
use AlexFigures\JsonApi\Contract\Data\ChangeSet;
use AlexFigures\JsonApi\Resource\Metadata\AttributeMetadata;
use AlexFigures\JsonApi\Resource\Metadata\ResourceMetadata;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

final class SerializerEntityInstantiatorTest extends TestCase
{
    private EntityManagerInterface $em;
    private ManagerRegistry $managerRegistry;
    private PropertyAccessor $accessor;
    private SerializerEntityInstantiator $instantiator;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->managerRegistry = $this->createMock(ManagerRegistry::class);
        $this->managerRegistry->method('getManagerForClass')->willReturn($this->em);
        $this->accessor = new PropertyAccessor();
        $this->instantiator = new SerializerEntityInstantiator($this->managerRegistry, $this->accessor);
    }

    public function testYamlGroupsControlConstructorAndUpdateDenormalization(): void
    {
        $class = \AlexFigures\JsonApi\Tests\Unit\Bridge\Doctrine\Instantiator\Fixtures\YamlWriteModel::class;
        $factory = new \Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory(new \Symfony\Component\Serializer\Mapping\Loader\YamlFileLoader(__DIR__ . '/Fixtures/serializer.yaml'));
        $instantiator = new SerializerEntityInstantiator($this->managerRegistry, $this->accessor, $factory);
        $metadata = new ResourceMetadata(type: 'yaml-models', class: $class, attributes: ['headline' => new AttributeMetadata('headline', 'title')], relationships: [], denormalizationContext: ['groups' => ['write']]);
        $entity = $instantiator->instantiate($class, $metadata, new ChangeSet(['headline' => 'Created']))['entity'];
        self::assertInstanceOf($class, $entity);
        self::assertSame('Created', $entity->title);
        $instantiator->denormalizer()->denormalize(['title' => 'Updated'], $class, null, ['groups' => ['write'], 'object_to_populate' => $entity, 'allow_extra_attributes' => false]);
        self::assertSame('Updated', $entity->title);
        try {
            $instantiator->denormalizer()->denormalize(['protectedValue' => 'leak'], $class, null, ['groups' => ['write'], 'object_to_populate' => $entity, 'allow_extra_attributes' => false]);
            self::fail('Read-only YAML group must be rejected.');
        } catch (\Symfony\Component\Serializer\Exception\ExtraAttributesException) {
            self::assertSame('original', $entity->protectedValue);
        }
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
            denormalizationContext: ['groups' => ['entity:write']],
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
}

// Test fixtures

class SimpleEntity
{
    public ?string $name = null;
}

class EntityWithConstructor
{
    public Uuid $uuid;

    public function __construct(#[Groups(['entity:write'])]
        public string $name, #[Groups(['entity:write'])]
        public string $email)
    {
        $this->uuid = Uuid::v7();
    }
}
