<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Functional\Relationships;

use AlexFigures\Symfony\Tests\Functional\JsonApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class RelationshipGetTest extends JsonApiTestCase
{
    public function testToOneRelationshipLinkage(): void
    {
        $request = Request::create('/api/articles/1/relationships/author', 'GET');
        $response = $this->relationshipGetController()($request, 'articles', '1', 'author');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{data: array{type: string, id: string}} $document */
        $document = $this->decode($response);

        self::assertSame(['type' => 'authors', 'id' => '1'], $document['data']);
    }

    public function testToManyRelationshipLinkageReturnsIdentifiers(): void
    {
        $request = Request::create('/api/articles/1/relationships/tags', 'GET');
        $response = $this->relationshipGetController()($request, 'articles', '1', 'tags');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{data: list<array{type: string, id: string}>} $document */
        $document = $this->decode($response);

        self::assertCount(2, $document['data']);
        self::assertSame('tags', $document['data'][0]['type']);
    }
}
