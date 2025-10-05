<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Functional;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class CollectionGetTest extends JsonApiTestCase
{
    public function testCollectionReturnsDocument(): void
    {
        $request = Request::create('/api/articles', 'GET');
        $response = ($this->collectionController())($request, 'articles');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('application/vnd.api+json', $response->headers->get('Content-Type'));

        $document = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('data', $document);
        self::assertCount(15, $document['data']);
        self::assertSame('http://localhost/api/articles', $document['links']['self']);
        self::assertSame(15, $document['meta']['total']);
        self::assertSame(1, $document['meta']['page']);
        self::assertSame(25, $document['meta']['size']);
        self::assertNotEmpty($document['links']['first']);
        self::assertNotEmpty($document['links']['last']);
    }
}
