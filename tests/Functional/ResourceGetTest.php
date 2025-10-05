<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Functional;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResourceGetTest extends JsonApiTestCase
{
    public function testSingleResourceResponse(): void
    {
        $request = Request::create('/api/articles/1', 'GET');
        $response = ($this->resourceController())($request, 'articles', '1');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('application/vnd.api+json', $response->headers->get('Content-Type'));

        $document = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('articles', $document['data']['type']);
        self::assertSame('1', $document['data']['id']);
        self::assertArrayHasKey('title', $document['data']['attributes']);
        self::assertArrayHasKey('createdAt', $document['data']['attributes']);
        self::assertSame('http://localhost/api/articles/1', $document['data']['links']['self']);
        self::assertSame('http://localhost/api/articles/1', $document['links']['self']);
    }
}
