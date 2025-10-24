<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Bridge\Serializer\Normalizer;

use AlexFigures\Symfony\Bridge\Serializer\Normalizer\RelationshipIgnoringDenormalizer;
use AlexFigures\Symfony\Resource\Attribute\Relationship;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

final class RelationshipIgnoringDenormalizerTest extends TestCase
{
    private RelationshipIgnoringDenormalizer $denormalizer;
    private DenormalizerInterface $innerDenormalizer;

    protected function setUp(): void
    {
        $this->denormalizer = new RelationshipIgnoringDenormalizer();

        // Create a mock inner denormalizer
        $this->innerDenormalizer = $this->createMock(DenormalizerInterface::class);
        $this->denormalizer->setDenormalizer($this->innerDenormalizer);
    }

    public function testSupportsArrayData(): void
    {
        $supports = $this->denormalizer->supportsDenormalization(
            ['name' => 'Test'],
            TestEntity::class
        );

        self::assertTrue($supports);
    }

    public function testDoesNotSupportNonArrayData(): void
    {
        $supports = $this->denormalizer->supportsDenormalization(
            'string',
            TestEntity::class
        );

        self::assertFalse($supports);
    }

    public function testDoesNotSupportNonExistentClass(): void
    {
        $supports = $this->denormalizer->supportsDenormalization(
            ['name' => 'Test'],
            'NonExistentClass'
        );

        self::assertFalse($supports);
    }

    public function testFiltersOutRelationshipProperties(): void
    {
        $data = [
            'name' => 'Test Name',
            'author' => ['id' => '123'], // This should be filtered out
            'description' => 'Test Description',
        ];

        $this->innerDenormalizer
            ->expects(self::once())
            ->method('denormalize')
            ->with(
                [
                    'name' => 'Test Name',
                    'description' => 'Test Description',
                    // 'author' should be filtered out
                ],
                TestEntity::class,
                null,
                self::anything()
            )
            ->willReturn(new TestEntity());

        $this->denormalizer->denormalize($data, TestEntity::class);
    }

    public function testPreservesNonRelationshipProperties(): void
    {
        $data = [
            'name' => 'Test Name',
            'description' => 'Test Description',
        ];

        $this->innerDenormalizer
            ->expects(self::once())
            ->method('denormalize')
            ->with(
                $data, // All properties should be preserved
                TestEntity::class,
                null,
                self::anything()
            )
            ->willReturn(new TestEntity());

        $this->denormalizer->denormalize($data, TestEntity::class);
    }

    public function testAvoidsInfiniteRecursion(): void
    {
        $context = ['RELATIONSHIP_IGNORING_DENORMALIZER_ALREADY_CALLED' => true];

        $supports = $this->denormalizer->supportsDenormalization(
            ['name' => 'Test'],
            TestEntity::class,
            null,
            $context
        );

        self::assertFalse($supports);
    }

    public function testGetSupportedTypes(): void
    {
        $types = $this->denormalizer->getSupportedTypes(null);

        self::assertArrayHasKey('object', $types);
        self::assertTrue($types['object']);
        self::assertArrayHasKey('*', $types);
        self::assertFalse($types['*']);
    }
}

// Test fixtures

class TestEntity
{
    public string $name = '';
    public string $description = '';

    #[Relationship(targetType: 'authors')]
    public ?TestAuthor $author = null;
}

class TestAuthor
{
    public string $id = '';
    public string $name = '';
}
