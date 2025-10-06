<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Unit\Http\Safety;

use JsonApi\Symfony\Http\Safety\RequestComplexityScorer;
use JsonApi\Symfony\Query\Criteria;
use JsonApi\Symfony\Query\Pagination;
use JsonApi\Symfony\Query\Sorting;
use PHPUnit\Framework\TestCase;

final class RequestComplexityScorerTest extends TestCase
{
    public function testScoresIncludesFieldsSortsAndPagination(): void
    {
        $criteria = new Criteria(new Pagination(2, 7));
        $criteria->include = ['author', 'comments.author', 'comments.author.profile'];
        $criteria->fields = [
            'articles' => ['title', 'body', 'slug'],
            'people' => ['name'],
        ];
        $criteria->sort = [
            new Sorting('title', false),
            new Sorting('createdAt', true),
        ];

        $scorer = new RequestComplexityScorer();

        $score = $scorer->score($criteria);

        // include score: 1^2 + 2^2 + 3^2 = 14
        // fields score: 3 + 1 = 4
        // sort score: 2 * 2 = 4
        // pagination score: size 7
        self::assertSame(29, $score);
    }
}
