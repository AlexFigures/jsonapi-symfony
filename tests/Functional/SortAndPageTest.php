<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Functional;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class SortAndPageTest extends JsonApiTestCase
{
    public function testPaginationAndSorting(): void
    {
        $request = Request::create('/api/articles', 'GET', [
            'page' => ['number' => 2, 'size' => 5],
            'sort' => '-createdAt',
        ]);

        $response = ($this->collectionController())($request, 'articles');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{data: list<array<string, mixed>>, links: array<string, string>} $document */
        $document = $this->decode($response);

        self::assertCount(5, $document['data']);
        self::assertSame('10', $document['data'][0]['id']);
        self::assertSame('6', $document['data'][4]['id']);
        $expectedFirst = 'http://localhost/api/articles?page%5Bnumber%5D=1&page%5Bsize%5D=5&sort=-createdAt';
        $expectedLast = 'http://localhost/api/articles?page%5Bnumber%5D=3&page%5Bsize%5D=5&sort=-createdAt';
        self::assertSame($expectedFirst, $document['links']['first']);
        self::assertSame($expectedLast, $document['links']['last']);
        self::assertSame($expectedFirst, $document['links']['prev']);
        self::assertSame($expectedLast, $document['links']['next']);
    }
}
