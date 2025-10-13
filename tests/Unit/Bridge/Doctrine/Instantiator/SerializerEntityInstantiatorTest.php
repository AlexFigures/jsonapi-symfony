<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Bridge\Doctrine\Instantiator;

use AlexFigures\Symfony\Bridge\Doctrine\Instantiator\SerializerEntityInstantiator;
use AlexFigures\Symfony\Contract\Data\ChangeSet;
use AlexFigures\Symfony\Resource\Metadata\AttributeMetadata;
use AlexFigures\Symfony\Resource\Metadata\ResourceMetadata;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Serializer\Annotation\Groups;
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

    #[Groups(['entity:write'])]
    public string $name;

    #[Groups(['entity:write'])]
    public string $email;

    public function __construct(string $name, string $email)
    {
        $this->uuid = Uuid::v7();
        $this->name = $name;
        $this->email = $email;
    }
}
