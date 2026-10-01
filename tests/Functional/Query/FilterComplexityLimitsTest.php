<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Functional\Query;

use AlexFigures\Symfony\Filter\Ast\Between;
use AlexFigures\Symfony\Filter\Ast\Comparison;
use AlexFigures\Symfony\Filter\Ast\Conjunction;
use AlexFigures\Symfony\Filter\Ast\Disjunction;
use AlexFigures\Symfony\Filter\Ast\Group;
use AlexFigures\Symfony\Filter\Ast\NullCheck;
use AlexFigures\Symfony\Filter\Parser\FilterParser;
use AlexFigures\Symfony\Filter\Validation\FilterComplexityAnalyzer;
use AlexFigures\Symfony\Http\Controller\CollectionController;
use AlexFigures\Symfony\Http\Controller\Support\JsonApiResponseFactory;
use AlexFigures\Symfony\Http\Controller\Support\OperationValidator;
use AlexFigures\Symfony\Http\Exception\BadRequestException;
use AlexFigures\Symfony\Http\Request\FilteringWhitelist;
use AlexFigures\Symfony\Http\Request\PaginationConfig;
use AlexFigures\Symfony\Http\Request\QueryParser;
use AlexFigures\Symfony\Http\Request\SortingWhitelist;
use AlexFigures\Symfony\Http\Safety\LimitsEnforcer;
use AlexFigures\Symfony\Http\Safety\RequestComplexityScorer;
use AlexFigures\Symfony\Query\Criteria;
use AlexFigures\Symfony\Tests\Functional\JsonApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;

final class FilterComplexityLimitsTest extends JsonApiTestCase
{
    public function testAllNodeKindsAndOperandCardinalityAreCounted(): void
    {
        $ast = new Group(new Conjunction([
            new Between('author.age', 1, 2),
            new NullCheck('author.name', true),
            new Disjunction([new Comparison('name', 'in', ['a', 'b', 'c'])]),
        ]));
        $metrics = (new FilterComplexityAnalyzer())->analyze($ast);
        self::assertSame(4, $metrics->depth);
        self::assertSame(6, $metrics->nodes);
        self::assertSame(5, $metrics->operands);
        self::assertSame(2, $metrics->pathHops);
        $criteria = new Criteria();
        $criteria->filter = $ast;
        self::assertSame($criteria->pagination->size + 15, (new RequestComplexityScorer())->score($criteria));
    }

    #[DataProvider('excessiveFilters')]
    public function testExcessiveFiltersAreRejectedBeforeRepositoryAccess(array $filter, array $limits): void
    {
        $repository = $this->createMock(\AlexFigures\Symfony\Contract\Data\ResourceRepository::class);
        $repository->expects(self::never())->method('findCollection');
        $controller = new CollectionController($this->filterRegistry(), new OperationValidator($this->errorMapper()), new JsonApiResponseFactory(), $repository, $this->limitedParser($limits), $this->documentBuilder());
        try {
            $controller(Request::create('/api/generated-records', parameters: ['filter' => $filter]), 'generated-records');
            self::fail('Expected a filter rejection before SQL.');
        } catch (BadRequestException $exception) {
            self::assertSame(400, $exception->getStatusCode());
            self::assertSame('filter', $exception->getErrors()[0]->source->parameter);
        }
    }

    public static function excessiveFilters(): iterable
    {
        $deep = ['name' => 'a'];
        for ($i = 0; $i < 40; ++$i) {
            $deep = ['and' => [$deep]];
        }
        yield 'depth' => [$deep, []];
        foreach ([100, 500, 1000] as $count) {
            yield $count . ' leaves plus group' => [['or' => array_fill(0, $count, ['name' => 'a'])], []];
        }
        yield 'IN' => [['name' => ['in' => array_fill(0, 500, 'a')]], []];
        yield 'NOT IN' => [['name' => ['nin' => array_fill(0, 500, 'a')]], []];
        yield 'configured nodes' => [['or' => [['name' => 'a'], ['name' => 'b']]], ['filter_max_nodes' => 2]];
        yield 'configured operands' => [['name' => ['in' => ['a', 'b']]], ['filter_max_operands' => 1]];
    }

    public function testReasonableNestedFilterAndDisabledLimits(): void
    {
        $filter = ['and' => [['name' => 'a'], ['or' => [['name' => 'b'], ['name' => 'c']]]]];
        self::assertNotNull($this->limitedParser([])->parse('generated-records', Request::create('/', parameters: ['filter' => $filter]))->filter);
        self::assertNotNull($this->limitedParser(['filter_max_depth' => 0, 'filter_max_nodes' => 0, 'filter_max_operands' => 0])->parse('generated-records', Request::create('/', parameters: ['filter' => ['name' => ['in' => array_fill(0, 500, 'a')]]]))->filter);
    }

    public function testWeightedBudgetAlsoAccountsForFilters(): void
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Request too complex.');
        $this->limitedParser(['complexity_budget' => 27])->parse('generated-records', Request::create('/', parameters: ['filter' => ['name' => ['in' => ['a', 'b', 'c']]] ]));
    }

    private function filterRegistry(): \AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface
    {
        return new \AlexFigures\Symfony\Resource\Registry\ResourceRegistry([\AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\GeneratedRecord::class]);
    }

    private function limitedParser(array $config): QueryParser
    {
        return new QueryParser($this->filterRegistry(), new PaginationConfig(), new SortingWhitelist($this->filterRegistry()), new FilteringWhitelist($this->filterRegistry(), $this->errorMapper()), $this->errorMapper(), new FilterParser($config['filter_max_depth'] ?? 8), new LimitsEnforcer($this->errorMapper(), new RequestComplexityScorer(), $config));
    }
}
