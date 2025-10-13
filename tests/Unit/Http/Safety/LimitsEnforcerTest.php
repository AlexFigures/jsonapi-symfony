<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Http\Safety;

use AlexFigures\Symfony\Http\Error\ErrorBuilder;
use AlexFigures\Symfony\Http\Error\ErrorCodes;
use AlexFigures\Symfony\Http\Error\ErrorMapper;
use AlexFigures\Symfony\Http\Exception\BadRequestException;
use AlexFigures\Symfony\Http\Safety\LimitsEnforcer;
use AlexFigures\Symfony\Http\Safety\RequestComplexityScorer;
use AlexFigures\Symfony\Query\Criteria;
use AlexFigures\Symfony\Query\Pagination;
use AlexFigures\Symfony\Query\Sorting;
use PHPUnit\Framework\TestCase;

final class LimitsEnforcerTest extends TestCase
{
    public function testAllowsRequestWithinLimits(): void
    {
        $criteria = $this->createCriteria();

        $enforcer = $this->createEnforcer([
            'include_max_paths' => 3,
            'include_max_depth' => 3,
            'fields_max_total' => 5,
            'page_max_size' => 10,
            'complexity_budget' => 30,
            'included_max_resources' => 5,
        ]);

        $enforcer->enforce('articles', $criteria);

        // also ensure included resources count within limit does not throw
        $enforcer->assertIncludedCount(2);

        $this->addToAssertionCount(1);
    }

    public function testThrowsWhenIncludePathsExceedLimit(): void
    {
        $criteria = $this->createCriteria();
        $criteria->include = ['author', 'comments'];

        $enforcer = $this->createEnforcer(['include_max_paths' => 1]);

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Too many include paths.');

        try {
            $enforcer->enforce('articles', $criteria);
        } catch (BadRequestException $exception) {
            $this->assertError($exception, ErrorCodes::INVALID_PARAMETER, 'Only 1 include paths are allowed per request.', 'include');

            throw $exception;
        }
    }

    public function testThrowsWhenIncludeDepthExceedsLimit(): void
    {
        $criteria = $this->createCriteria();
        $criteria->include = ['comments.author'];

        $enforcer = $this->createEnforcer(['include_max_depth' => 1]);

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Include depth exceeded.');

        try {
            $enforcer->enforce('articles', $criteria);
        } catch (BadRequestException $exception) {
            $this->assertError($exception, ErrorCodes::INVALID_PARAMETER, 'Include depth cannot exceed 1.', 'include');

            throw $exception;
        }
    }

    public function testThrowsWhenFieldsTotalExceedsLimit(): void
    {
        $criteria = $this->createCriteria();
        $criteria->fields = [
            'articles' => ['title', 'body', 'slug'],
            'people' => ['name', 'email'],
        ];

        $enforcer = $this->createEnforcer(['fields_max_total' => 4]);

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Too many fields requested.');

        try {
            $enforcer->enforce('articles', $criteria);
        } catch (BadRequestException $exception) {
            $this->assertError($exception, ErrorCodes::INVALID_PARAMETER, 'A maximum of 4 fields can be requested.', 'fields');

            throw $exception;
        }
    }

    public function testThrowsWhenPageSizeExceedsLimit(): void
    {
        $criteria = $this->createCriteria();
        $criteria->pagination = new Pagination(1, 25);

        $enforcer = $this->createEnforcer(['page_max_size' => 20]);

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Page size too large.');

        try {
            $enforcer->enforce('articles', $criteria);
        } catch (BadRequestException $exception) {
            $this->assertError($exception, ErrorCodes::INVALID_PARAMETER, 'Page size cannot be greater than 20.', 'page[size]');

            throw $exception;
        }
    }

    public function testThrowsWhenComplexityBudgetIsExceeded(): void
    {
        $criteria = $this->createCriteria();
        $criteria->include = ['author', 'comments.author'];
        $criteria->fields = ['articles' => ['title'], 'people' => ['name']];
        $criteria->sort = [];
        $criteria->pagination = new Pagination(1, 6);

        $enforcer = $this->createEnforcer(['complexity_budget' => 10]);

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Request too complex.');

        $score = (new RequestComplexityScorer())->score($criteria);

        try {
            $enforcer->enforce('articles', $criteria);
        } catch (BadRequestException $exception) {
            $this->assertError(
                $exception,
                ErrorCodes::REQUEST_COMPLEXITY_EXCEEDED,
                sprintf('The request complexity score of %d exceeds the allowed budget of 10.', $score),
            );

            throw $exception;
        }
    }

    public function testAssertIncludedCountThrowsWhenLimitExceeded(): void
    {
        $enforcer = $this->createEnforcer(['included_max_resources' => 3]);

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Too many included resources.');

        try {
            $enforcer->assertIncludedCount(5);
        } catch (BadRequestException $exception) {
            $this->assertError(
                $exception,
                ErrorCodes::INCLUDED_RESOURCES_LIMIT,
                'The request would include more than 3 related resources.',
            );

            throw $exception;
        }
    }

    /**
     * @param array<string, int> $config
     */
    private function createEnforcer(array $config): LimitsEnforcer
    {
        $builder = new ErrorBuilder(false);
        $mapper = new ErrorMapper($builder);

        return new LimitsEnforcer($mapper, new RequestComplexityScorer(), $config);
    }

    private function createCriteria(): Criteria
    {
        $criteria = new Criteria(new Pagination(1, 5));
        $criteria->include = ['author'];
        $criteria->fields = ['articles' => ['title', 'body']];
        $criteria->sort = [new Sorting('title', false)];

        return $criteria;
    }

    private function assertError(BadRequestException $exception, string $code, string $detail, ?string $parameter = null): void
    {
        $errors = $exception->getErrors();
        self::assertCount(1, $errors);
        $error = $errors[0];

        self::assertSame($code, $error->code);
        self::assertSame($detail, $error->detail);

        if ($parameter !== null) {
            self::assertSame($parameter, $error->source?->parameter);
        }
    }
}
