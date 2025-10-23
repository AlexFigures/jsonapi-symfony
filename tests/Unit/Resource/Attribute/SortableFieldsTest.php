<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Resource\Attribute;

use AlexFigures\Symfony\Resource\Attribute\SortableField;
use AlexFigures\Symfony\Resource\Attribute\SortableFields;
use PHPUnit\Framework\TestCase;

final class SortableFieldsTest extends TestCase
{
    public function testConstructorStoresFields(): void
    {
        $fields = ['name', 'createdAt', 'updatedAt'];
        $attribute = new SortableFields($fields);

        self::assertSame(['name', 'createdAt', 'updatedAt'], $attribute->getAllowedFields());
        self::assertCount(3, $attribute->getFields());
    }

    public function testConstructorWithSortableFieldObjects(): void
    {
        $fields = [
            new SortableField('name'),
            new SortableField('createdAt'),
        ];
        $attribute = new SortableFields($fields);

        self::assertSame(['name', 'createdAt'], $attribute->getAllowedFields());
        self::assertInstanceOf(SortableField::class, $attribute->getFieldConfig('name'));
    }

    public function testConstructorReindexesArray(): void
    {
        $fields = [2 => 'name', 5 => 'createdAt', 10 => 'updatedAt'];
        $attribute = new SortableFields($fields);

        self::assertSame(['name', 'createdAt', 'updatedAt'], $attribute->getAllowedFields());
    }

    public function testEmptyFieldsArray(): void
    {
        $attribute = new SortableFields([]);

        self::assertSame([], $attribute->getAllowedFields());
        self::assertSame([], $attribute->getFields());
    }

    public function testSingleField(): void
    {
        $attribute = new SortableFields(['name']);

        self::assertSame(['name'], $attribute->getAllowedFields());
    }

    public function testIsAllowedForDirectField(): void
    {
        $attribute = new SortableFields(['name', 'email']);

        self::assertTrue($attribute->isAllowed('name'));
        self::assertTrue($attribute->isAllowed('email'));
        self::assertFalse($attribute->isAllowed('unknown'));
    }

    public function testGetFieldConfig(): void
    {
        $nameField = new SortableField('name');
        $attribute = new SortableFields([$nameField, 'email']);

        $nameConfig = $attribute->getFieldConfig('name');
        $emailConfig = $attribute->getFieldConfig('email');
        $unknownConfig = $attribute->getFieldConfig('unknown');

        self::assertSame($nameField, $nameConfig);
        self::assertInstanceOf(SortableField::class, $emailConfig);
        self::assertSame('email', $emailConfig->field);
        self::assertNull($unknownConfig);
    }

    public function testGetFields(): void
    {
        $nameField = new SortableField('name');
        $attribute = new SortableFields([$nameField, 'email']);

        $allFields = $attribute->getFields();

        self::assertCount(2, $allFields);
        self::assertArrayHasKey('name', $allFields);
        self::assertArrayHasKey('email', $allFields);
        self::assertSame($nameField, $allFields['name']);
        self::assertInstanceOf(SortableField::class, $allFields['email']);
    }

    public function testInvalidFieldType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SortableFields expects array of SortableField instances or strings, got int');

        new SortableFields([123]);
    }

    public function testDuplicateFieldNames(): void
    {
        // When duplicate field names are provided, the last one should win
        $field1 = new SortableField('name');
        $field2 = new SortableField('name', customHandler: 'custom.handler');

        $attribute = new SortableFields([$field1, $field2]);

        $config = $attribute->getFieldConfig('name');
        self::assertSame($field2, $config);
        self::assertSame('custom.handler', $config->customHandler);
    }

    public function testIsAllowedWithInheritanceFromRelationship(): void
    {
        $registry = $this->createMock(\AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface::class);

        // Author resource has 'name' and 'email' sortable fields
        $authorSortableFields = new SortableFields(['name', 'email']);
        $authorMetadata = new \AlexFigures\Symfony\Resource\Metadata\ResourceMetadata(
            type: 'authors',
            class: \AlexFigures\Symfony\Tests\Fixtures\Model\Author::class,
            attributes: [],
            relationships: [],
            sortableFields: $authorSortableFields,
        );

        // Article resource has 'title' and 'author' (with inherit: true)
        $articleSortableFields = new SortableFields([
            'title',
            new SortableField('author', inherit: true),
        ]);
        $articleMetadata = new \AlexFigures\Symfony\Resource\Metadata\ResourceMetadata(
            type: 'articles',
            class: \AlexFigures\Symfony\Tests\Fixtures\Model\Article::class,
            attributes: [],
            relationships: [
                'author' => new \AlexFigures\Symfony\Resource\Metadata\RelationshipMetadata(
                    name: 'author',
                    targetType: 'authors',
                    toMany: false,
                    linkingPolicy: \AlexFigures\Symfony\Resource\Metadata\RelationshipLinkingPolicy::VERIFY,
                ),
            ],
            sortableFields: $articleSortableFields,
        );

        $registry->method('hasType')->willReturnCallback(
            fn (string $type) => in_array($type, ['articles', 'authors'], true)
        );
        $registry->method('getByType')->willReturnCallback(
            fn (string $type) => match ($type) {
                'articles' => $articleMetadata,
                'authors' => $authorMetadata,
            }
        );

        // Direct field
        self::assertTrue($articleSortableFields->isAllowed('title', $registry, 'articles'));

        // Inherited fields from author relationship
        self::assertTrue($articleSortableFields->isAllowed('author.name', $registry, 'articles'));
        self::assertTrue($articleSortableFields->isAllowed('author.email', $registry, 'articles'));

        // Non-existent field
        self::assertFalse($articleSortableFields->isAllowed('author.unknown', $registry, 'articles'));
    }

    public function testIsAllowedWithInheritanceAndExcept(): void
    {
        $registry = $this->createMock(\AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface::class);

        // Author resource has 'name', 'email', 'password' sortable fields
        $authorSortableFields = new SortableFields(['name', 'email', 'password']);
        $authorMetadata = new \AlexFigures\Symfony\Resource\Metadata\ResourceMetadata(
            type: 'authors',
            class: \AlexFigures\Symfony\Tests\Fixtures\Model\Author::class,
            attributes: [],
            relationships: [],
            sortableFields: $authorSortableFields,
        );

        // Article resource inherits from author but excludes 'password'
        $articleSortableFields = new SortableFields([
            'title',
            new SortableField('author', inherit: true, except: ['password']),
        ]);
        $articleMetadata = new \AlexFigures\Symfony\Resource\Metadata\ResourceMetadata(
            type: 'articles',
            class: \AlexFigures\Symfony\Tests\Fixtures\Model\Article::class,
            attributes: [],
            relationships: [
                'author' => new \AlexFigures\Symfony\Resource\Metadata\RelationshipMetadata(
                    name: 'author',
                    targetType: 'authors',
                    toMany: false,
                    linkingPolicy: \AlexFigures\Symfony\Resource\Metadata\RelationshipLinkingPolicy::VERIFY,
                ),
            ],
            sortableFields: $articleSortableFields,
        );

        $registry->method('hasType')->willReturnCallback(
            fn (string $type) => in_array($type, ['articles', 'authors'], true)
        );
        $registry->method('getByType')->willReturnCallback(
            fn (string $type) => match ($type) {
                'articles' => $articleMetadata,
                'authors' => $authorMetadata,
            }
        );

        // Allowed inherited fields
        self::assertTrue($articleSortableFields->isAllowed('author.name', $registry, 'articles'));
        self::assertTrue($articleSortableFields->isAllowed('author.email', $registry, 'articles'));

        // Excluded field
        self::assertFalse($articleSortableFields->isAllowed('author.password', $registry, 'articles'));
    }

    public function testIsAllowedWithDepthLimit(): void
    {
        $registry = $this->createMock(\AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface::class);

        // Create a simple metadata structure
        $authorSortableFields = new SortableFields(['name']);
        $authorMetadata = new \AlexFigures\Symfony\Resource\Metadata\ResourceMetadata(
            type: 'authors',
            class: \AlexFigures\Symfony\Tests\Fixtures\Model\Author::class,
            attributes: [],
            relationships: [],
            sortableFields: $authorSortableFields,
        );

        $articleSortableFields = new SortableFields([
            new SortableField('author', inherit: true),
        ]);
        $articleMetadata = new \AlexFigures\Symfony\Resource\Metadata\ResourceMetadata(
            type: 'articles',
            class: \AlexFigures\Symfony\Tests\Fixtures\Model\Article::class,
            attributes: [],
            relationships: [
                'author' => new \AlexFigures\Symfony\Resource\Metadata\RelationshipMetadata(
                    name: 'author',
                    targetType: 'authors',
                    toMany: false,
                    linkingPolicy: \AlexFigures\Symfony\Resource\Metadata\RelationshipLinkingPolicy::VERIFY,
                ),
            ],
            sortableFields: $articleSortableFields,
        );

        $registry->method('hasType')->willReturn(true);
        $registry->method('getByType')->willReturnCallback(
            fn (string $type) => match ($type) {
                'articles' => $articleMetadata,
                'authors' => $authorMetadata,
            }
        );

        // Depth 0 and 1 should work
        self::assertTrue($articleSortableFields->isAllowed('author.name', $registry, 'articles', 0));
        self::assertTrue($articleSortableFields->isAllowed('author.name', $registry, 'articles', 1));

        // Depth >= 2 should be rejected
        self::assertFalse($articleSortableFields->isAllowed('author.name', $registry, 'articles', 2));
        self::assertFalse($articleSortableFields->isAllowed('author.name', $registry, 'articles', 3));
    }

    public function testIsAllowedWithoutInheritance(): void
    {
        $registry = $this->createMock(\AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface::class);

        // Article resource has 'author' field but WITHOUT inherit: true
        $articleSortableFields = new SortableFields([
            'title',
            new SortableField('author', inherit: false), // No inheritance
        ]);

        // Should not allow nested fields when inherit is false
        self::assertFalse($articleSortableFields->isAllowed('author.name', $registry, 'articles'));
    }
}
