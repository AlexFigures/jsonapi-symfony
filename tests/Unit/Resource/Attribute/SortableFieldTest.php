<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Resource\Attribute;

use AlexFigures\Symfony\Resource\Attribute\SortableField;
use PHPUnit\Framework\TestCase;

final class SortableFieldTest extends TestCase
{
    public function testConstructorWithDefaults(): void
    {
        $field = new SortableField('title');

        self::assertSame('title', $field->field);
        self::assertNull($field->customHandler);
        self::assertFalse($field->inherit);
        self::assertSame([], $field->except);
    }

    public function testConstructorWithCustomHandler(): void
    {
        $field = new SortableField('relevance', customHandler: 'app.sort.relevance');

        self::assertSame('relevance', $field->field);
        self::assertSame('app.sort.relevance', $field->customHandler);
        self::assertTrue($field->hasCustomHandler());
    }

    public function testConstructorWithInheritance(): void
    {
        $field = new SortableField('author', inherit: true);

        self::assertSame('author', $field->field);
        self::assertTrue($field->shouldInherit());
        self::assertSame([], $field->except);
    }

    public function testConstructorWithExcept(): void
    {
        $field = new SortableField('author', inherit: true, except: ['email', 'password']);

        self::assertSame('author', $field->field);
        self::assertTrue($field->shouldInherit());
        self::assertSame(['email', 'password'], $field->except);
    }

    public function testHasCustomHandler(): void
    {
        $fieldWithHandler = new SortableField('relevance', customHandler: 'app.sort.relevance');
        $fieldWithoutHandler = new SortableField('title');

        self::assertTrue($fieldWithHandler->hasCustomHandler());
        self::assertFalse($fieldWithoutHandler->hasCustomHandler());
    }

    public function testShouldInherit(): void
    {
        $inheritField = new SortableField('author', inherit: true);
        $normalField = new SortableField('title');

        self::assertTrue($inheritField->shouldInherit());
        self::assertFalse($normalField->shouldInherit());
    }

    public function testIsExcluded(): void
    {
        $field = new SortableField('author', inherit: true, except: ['email', 'password']);

        self::assertTrue($field->isExcluded('email'));
        self::assertTrue($field->isExcluded('password'));
        self::assertFalse($field->isExcluded('name'));
        self::assertFalse($field->isExcluded('id'));
    }

    public function testExceptArrayIsReindexed(): void
    {
        $except = [2 => 'email', 5 => 'password', 10 => 'secret'];
        $field = new SortableField('author', inherit: true, except: $except);

        self::assertSame(['email', 'password', 'secret'], $field->except);
    }

    public function testEmptyExceptArray(): void
    {
        $field = new SortableField('author', inherit: true, except: []);

        self::assertSame([], $field->except);
        self::assertFalse($field->isExcluded('anything'));
    }
}
