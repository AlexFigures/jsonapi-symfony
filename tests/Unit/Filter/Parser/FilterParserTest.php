<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Unit\Filter\Parser;

use JsonApi\Symfony\Filter\Ast\Comparison;
use JsonApi\Symfony\Filter\Ast\Conjunction;
use JsonApi\Symfony\Filter\Ast\Disjunction;
use JsonApi\Symfony\Filter\Ast\NullCheck;
use JsonApi\Symfony\Filter\Parser\FilterParser;
use PHPUnit\Framework\TestCase;

final class FilterParserTest extends TestCase
{
    public function testReturnsNullWhenNoFiltersAreProvided(): void
    {
        $parser = new FilterParser();

        self::assertNull($parser->parse([]));
    }

    public function testParsesSimpleEqualityComparison(): void
    {
        $parser = new FilterParser();

        $ast = $parser->parse(['title' => 'Dune']);

        self::assertInstanceOf(Comparison::class, $ast);
        self::assertSame('title', $ast->fieldPath);
        self::assertSame('eq', $ast->operator);
        self::assertSame(['Dune'], $ast->values);
    }

    public function testMergesMultipleFieldsIntoConjunction(): void
    {
        $parser = new FilterParser();

        $ast = $parser->parse([
            'title' => 'Dune',
            'year' => ['gte' => 1965],
        ]);

        self::assertInstanceOf(Conjunction::class, $ast);
        self::assertCount(2, $ast->children);
        self::assertContainsOnlyInstancesOf(Comparison::class, $ast->children);
    }

    public function testParsesInOperatorFromSequentialValues(): void
    {
        $parser = new FilterParser();

        $ast = $parser->parse(['tags' => ['sci-fi', 'classic']]);

        self::assertInstanceOf(Comparison::class, $ast);
        self::assertSame('in', $ast->operator);
        self::assertSame(['sci-fi', 'classic'], $ast->values);
    }

    public function testParsesDisjunctionGroup(): void
    {
        $parser = new FilterParser();

        $ast = $parser->parse([
            'or' => [
                ['title' => 'Dune'],
                ['title' => 'Dune Messiah'],
            ],
        ]);

        self::assertInstanceOf(Disjunction::class, $ast);
        self::assertCount(2, $ast->children);
        self::assertContainsOnlyInstancesOf(Comparison::class, $ast->children);
    }

    public function testParsesNullCheck(): void
    {
        $parser = new FilterParser();

        $ast = $parser->parse(['publishedAt' => ['isnull' => true]]);

        self::assertInstanceOf(NullCheck::class, $ast);
        self::assertTrue($ast->isNull);
    }

    public function testCombinesMultipleOperatorsForSameField(): void
    {
        $parser = new FilterParser();

        $ast = $parser->parse(['year' => ['gte' => 1965, 'lte' => 1985]]);

        self::assertInstanceOf(Conjunction::class, $ast);
        self::assertCount(2, $ast->children);
        self::assertContainsOnlyInstancesOf(Comparison::class, $ast->children);
    }
}
