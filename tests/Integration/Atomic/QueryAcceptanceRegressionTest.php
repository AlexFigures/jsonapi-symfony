<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Integration\Atomic;

use AlexFigures\JsonApi\Filter\Parser\FilterParser;
use AlexFigures\JsonApi\Http\Exception\JsonApiHttpException;
use AlexFigures\JsonApi\Http\Request\FilteringWhitelist;
use AlexFigures\JsonApi\Http\Request\PaginationConfig;
use AlexFigures\JsonApi\Http\Request\QueryParser;
use AlexFigures\JsonApi\Http\Request\SortingWhitelist;
use AlexFigures\JsonApi\Tests\Integration\Fixtures\Entity\GeneratedRecord;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;

/** PostgreSQL FILTER-001..005, ALIAS-001, QUERY-001/002, ERROR-001, SORT-001. */
final class QueryAcceptanceRegressionTest extends DoctrineAtomicTestCase
{
    private QueryParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new QueryParser($this->registry, new PaginationConfig(), new SortingWhitelist($this->registry), new FilteringWhitelist($this->registry, $this->errorMapper), $this->errorMapper, new FilterParser());
        foreach (['Alpha', 'Beta', 'Gamma'] as $index => $name) {
            $record = new GeneratedRecord();
            $record->name = $name;
            $record->publishedAt = $index === 0 ? null : new \DateTimeImmutable('2024-01-01T00:00:00Z');
            $this->em->persist($record);
        }
        $this->em->flush();
        $this->em->clear();
    }

    #[DataProvider('filters')]
    public function testFiltersCompileAndExecute(array $filter, array $expected): void
    {
        $criteria = $this->parser->parse('generated-records', Request::create('/api/generated-records', parameters: ['filter' => $filter]));
        $slice = $this->repository->findCollection('generated-records', $criteria);
        self::assertSame($expected, array_map(static fn (GeneratedRecord $record): string => $record->name, $slice->items));
    }

    public static function filters(): iterable
    {
        yield 'neq' => [['name' => ['neq' => 'Alpha']], ['Beta', 'Gamma']];
        yield 'ne' => [['name' => ['ne' => 'Alpha']], ['Beta', 'Gamma']];
        yield 'between' => [['id' => ['between' => ['1', '2']]], ['Alpha', 'Beta']];
        yield 'nullable alias' => [['published-at' => ['isnull' => 'true']], ['Alpha']];
        yield 'not null alias' => [['published-at' => ['isnull' => 'false']], ['Beta', 'Gamma']];
        yield 'ILIKE' => [['name' => ['ilike' => 'ALPH']], ['Alpha']];
        yield 'empty in' => [['id' => ['in' => '']], []];
        yield 'empty not in' => [['id' => ['nin' => '']], ['Alpha', 'Beta', 'Gamma']];
    }

    #[DataProvider('invalidQueries')]
    public function testMalformedQueriesRejectedBeforeDoctrine(array $query, string $parameter): void
    {
        try {
            $this->parser->parse('generated-records', Request::create('/api/generated-records', parameters: $query));
            self::fail('The query must be rejected at the HTTP boundary.');
        } catch (JsonApiHttpException $exception) {
            self::assertSame(400, $exception->getStatusCode());
            self::assertSame($parameter, $exception->getErrors()[0]->source->parameter);
            self::assertNotEmpty($exception->getErrors()[0]->title);
        }
    }

    public static function invalidQueries(): iterable
    {
        yield 'page list' => [['page' => ['1']], 'page'];
        yield 'page scalar' => [['page' => '1'], 'page'];
        yield 'page number object' => [['page' => ['number' => ['x' => '1']]], 'page[number]'];
        yield 'operand object' => [['filter' => ['name' => ['eq' => ['x' => 'value']]]], 'filter'];
        yield 'wrong integer' => [['filter' => ['id' => ['eq' => 'abc']]], 'filter'];
        yield 'between one' => [['filter' => ['id' => ['between' => ['1']]]], 'filter'];
        yield 'between three' => [['filter' => ['id' => ['between' => ['1', '2', '3']]]], 'filter'];
        yield 'between structured' => [['filter' => ['id' => ['between' => [['x'], '2']]]], 'filter'];
        yield 'between invalid integer' => [['filter' => ['id' => ['between' => ['abc', '2']]]], 'filter'];
        yield 'unknown field title' => [['filter' => ['unknown' => 'value']], 'filter'];
        $filter = ['name' => 'Alpha'];
        for ($i = 0; $i < 12; ++$i) {
            $filter = ['and' => [$filter]];
        }
        yield 'excessive depth' => [['filter' => $filter], 'filter'];
    }

    public function testAliasSortingAddsIdTieBreakerWithoutChangingClientCriteria(): void
    {
        $request = Request::create('/api/generated-records', parameters: ['sort' => '-published-at', 'page' => ['size' => '1']]);
        $criteria = $this->parser->parse('generated-records', $request);
        $criteria->pagination->number = 2;
        $first = $this->repository->findCollection('generated-records', $criteria)->items[0]->id;
        $this->em->getConnection()->executeStatement('UPDATE generated_records SET name = ? WHERE id = ?', ['Other', $first]);
        $this->em->clear();
        $second = $this->repository->findCollection('generated-records', $criteria)->items[0]->id;
        self::assertSame($first, $second);
        self::assertCount(1, $criteria->sort);
        self::assertSame('published-at', $criteria->sort[0]->field);
    }
}
