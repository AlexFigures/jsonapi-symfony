<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Functional;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class FieldsTest extends JsonApiTestCase
{
    public function testSparseFieldsetOnResource(): void
    {
        $request = Request::create('/api/articles/1', 'GET', ['fields' => ['articles' => 'title']]);
        $response = ($this->resourceController())($request, 'articles', '1');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{data: array{attributes: array<string, mixed>}} $document */
        $document = $this->decode($response);

        self::assertSame(['title'], array_keys($document['data']['attributes']));
    }
}
