<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Resource\Attribute;

use AlexFigures\Symfony\Resource\Attribute\FilterableField;
use PHPUnit\Framework\TestCase;

final class FilterableFieldTest extends TestCase
{
    public function testConstructorWithDefaults(): void
    {
        $field = new FilterableField('title');

        self::assertSame('title', $field->field);
        self::assertSame([
            'eq', 'ne', 'gt', 'gte', 'lt', 'lte',
            'like', 'in', 'nin', 'null', 'nnull'
        ], $field->operators);
        self::assertNull($field->customHandler);
    }

    public function testConstructorWithCustomOperators(): void
    {
        $operators = ['eq', 'like'];
        $field = new FilterableField('title', $operators);

        self::assertSame('title', $field->field);
        self::assertSame($operators, $field->operators);
        self::assertNull($field->customHandler);
    }

    public function testConstructorWithCustomHandler(): void
    {
        $field = new FilterableField('content', ['eq'], 'app.filter.fulltext');

        self::assertSame('content', $field->field);
        self::assertSame(['eq'], $field->operators);
        self::assertSame('app.filter.fulltext', $field->customHandler);
    }

    public function testIsOperatorAllowed(): void
    {
        $field = new FilterableField('status', ['eq', 'in']);

        self::assertTrue($field->isOperatorAllowed('eq'));
        self::assertTrue($field->isOperatorAllowed('in'));
        self::assertFalse($field->isOperatorAllowed('like'));
        self::assertFalse($field->isOperatorAllowed('gt'));
    }

    public function testHasCustomHandler(): void
    {
        $fieldWithHandler = new FilterableField('content', [], 'app.filter.fulltext');
        $fieldWithoutHandler = new FilterableField('title');

        self::assertTrue($fieldWithHandler->hasCustomHandler());
        self::assertFalse($fieldWithoutHandler->hasCustomHandler());
    }

    public function testOperatorsArrayIsReindexed(): void
    {
        $operators = [2 => 'eq', 5 => 'like', 10 => 'in'];
        $field = new FilterableField('title', $operators);

        self::assertSame(['eq', 'like', 'in'], $field->operators);
    }

    public function testEmptyOperatorsArray(): void
    {
        $field = new FilterableField('title', []);

        self::assertSame([], $field->operators);
        self::assertFalse($field->isOperatorAllowed('eq'));
    }
}
