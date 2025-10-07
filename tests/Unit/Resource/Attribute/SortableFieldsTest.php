<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Unit\Resource\Attribute;

use JsonApi\Symfony\Resource\Attribute\SortableFields;
use PHPUnit\Framework\TestCase;

final class SortableFieldsTest extends TestCase
{
    public function testConstructorStoresFields(): void
    {
        $fields = ['name', 'createdAt', 'updatedAt'];
        $attribute = new SortableFields($fields);

        self::assertSame($fields, $attribute->fields);
    }

    public function testConstructorReindexesArray(): void
    {
        $fields = [2 => 'name', 5 => 'createdAt', 10 => 'updatedAt'];
        $attribute = new SortableFields($fields);

        self::assertSame(['name', 'createdAt', 'updatedAt'], $attribute->fields);
    }

    public function testEmptyFieldsArray(): void
    {
        $attribute = new SortableFields([]);

        self::assertSame([], $attribute->fields);
    }

    public function testSingleField(): void
    {
        $attribute = new SortableFields(['name']);

        self::assertSame(['name'], $attribute->fields);
    }
}

