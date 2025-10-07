<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Unit\Resource\Attribute;

use JsonApi\Symfony\Resource\Attribute\FilterableField;
use JsonApi\Symfony\Resource\Attribute\FilterableFields;
use PHPUnit\Framework\TestCase;

final class FilterableFieldsTest extends TestCase
{
    public function testConstructorWithStringFields(): void
    {
        $fields = new FilterableFields(['title', 'status']);

        self::assertTrue($fields->isAllowed('title'));
        self::assertTrue($fields->isAllowed('status'));
        self::assertFalse($fields->isAllowed('content'));
        self::assertSame(['title', 'status'], $fields->getAllowedFields());
    }

    public function testConstructorWithFilterableFieldObjects(): void
    {
        $titleField = new FilterableField('title', ['eq', 'like']);
        $statusField = new FilterableField('status', ['eq', 'in']);
        
        $fields = new FilterableFields([$titleField, $statusField]);

        self::assertTrue($fields->isAllowed('title'));
        self::assertTrue($fields->isAllowed('status'));
        self::assertFalse($fields->isAllowed('content'));
        self::assertSame(['title', 'status'], $fields->getAllowedFields());
    }

    public function testConstructorWithMixedFields(): void
    {
        $titleField = new FilterableField('title', ['eq', 'like']);
        
        $fields = new FilterableFields([$titleField, 'status']);

        self::assertTrue($fields->isAllowed('title'));
        self::assertTrue($fields->isAllowed('status'));
        self::assertSame(['title', 'status'], $fields->getAllowedFields());
    }

    public function testGetFieldConfig(): void
    {
        $titleField = new FilterableField('title', ['eq', 'like']);
        $fields = new FilterableFields([$titleField, 'status']);

        $titleConfig = $fields->getFieldConfig('title');
        $statusConfig = $fields->getFieldConfig('status');
        $unknownConfig = $fields->getFieldConfig('unknown');

        self::assertSame($titleField, $titleConfig);
        self::assertInstanceOf(FilterableField::class, $statusConfig);
        self::assertSame('status', $statusConfig->field);
        self::assertNull($unknownConfig);
    }

    public function testIsOperatorAllowed(): void
    {
        $titleField = new FilterableField('title', ['eq', 'like']);
        $fields = new FilterableFields([$titleField, 'status']);

        // Title field has restricted operators
        self::assertTrue($fields->isOperatorAllowed('title', 'eq'));
        self::assertTrue($fields->isOperatorAllowed('title', 'like'));
        self::assertFalse($fields->isOperatorAllowed('title', 'gt'));

        // Status field (string) has all operators by default
        self::assertTrue($fields->isOperatorAllowed('status', 'eq'));
        self::assertTrue($fields->isOperatorAllowed('status', 'gt'));
        self::assertTrue($fields->isOperatorAllowed('status', 'like'));

        // Unknown field
        self::assertFalse($fields->isOperatorAllowed('unknown', 'eq'));
    }

    public function testGetFields(): void
    {
        $titleField = new FilterableField('title', ['eq', 'like']);
        $fields = new FilterableFields([$titleField, 'status']);

        $allFields = $fields->getFields();

        self::assertCount(2, $allFields);
        self::assertArrayHasKey('title', $allFields);
        self::assertArrayHasKey('status', $allFields);
        self::assertSame($titleField, $allFields['title']);
        self::assertInstanceOf(FilterableField::class, $allFields['status']);
    }

    public function testEmptyFields(): void
    {
        $fields = new FilterableFields([]);

        self::assertSame([], $fields->getAllowedFields());
        self::assertFalse($fields->isAllowed('any'));
        self::assertNull($fields->getFieldConfig('any'));
        self::assertSame([], $fields->getFields());
    }

    public function testInvalidFieldType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('FilterableFields expects array of FilterableField instances or strings, got int');

        new FilterableFields([123]);
    }

    public function testDuplicateFieldNames(): void
    {
        // When duplicate field names are provided, the last one should win
        $field1 = new FilterableField('title', ['eq']);
        $field2 = new FilterableField('title', ['like']);
        
        $fields = new FilterableFields([$field1, $field2]);

        $config = $fields->getFieldConfig('title');
        self::assertSame($field2, $config);
        self::assertSame(['like'], $config->operators);
    }
}
