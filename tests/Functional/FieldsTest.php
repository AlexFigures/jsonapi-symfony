<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Functional;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class FieldsTest extends JsonApiTestCase
{
    public function testSparseFieldsetOnResource(): void
    {
        $request = Request::create('/api/articles/1', 'GET', ['fields' => ['articles' => 'title']]);
        $response = ($this->resourceController())($request, 'articles', '1');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(['title'], array_keys($document['data']['attributes']));
    }
}
