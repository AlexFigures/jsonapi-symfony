<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Functional\Relationships;

use JsonApi\Symfony\Http\Exception\NotFoundException;
use JsonApi\Symfony\Tests\Functional\JsonApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class RelatedGetTest extends JsonApiTestCase
{
    public function testToOneRelationshipReturnsResource(): void
    {
        $request = Request::create('/api/articles/1/author', 'GET');
        $response = $this->relatedController()($request, 'articles', '1', 'author');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('application/vnd.api+json', $response->headers->get('Content-Type'));

        /** @var array{data: array{type: string, id: string}} $document */
        $document = $this->decode($response);

        self::assertSame('authors', $document['data']['type']);
        self::assertSame('1', $document['data']['id']);
    }

    public function testToManyRelationshipSupportsPagination(): void
    {
        $request = Request::create('/api/articles/1/tags?page[number]=1&page[size]=1', 'GET');
        $response = $this->relatedController()($request, 'articles', '1', 'tags');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{meta: array{total: int, page: int, size: int}, data: list<array{type: string}>} $document */
        $document = $this->decode($response);

        self::assertSame(2, $document['meta']['total']);
        self::assertSame(1, $document['meta']['page']);
        self::assertSame(1, $document['meta']['size']);
        self::assertCount(1, $document['data']);
        self::assertSame('tags', $document['data'][0]['type']);
    }

    public function testUnknownRelationshipReturnsNotFound(): void
    {
        $request = Request::create('/api/articles/1/comments', 'GET');

        $this->expectException(NotFoundException::class);
        ($this->relatedController())($request, 'articles', '1', 'comments');
    }
}
