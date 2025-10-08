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

        /** @var array{
         *     data: array{
         *         type: string,
         *         id: string,
         *         attributes: array<string, mixed>,
         *         links: array<string, string>,
         *         relationships: array<string, array<string, mixed>>
         *     },
         *     links: array<string, string>
         * } $document
         */
        $document = $this->decode($response);

        self::assertSame('articles', $document['data']['type']);
        self::assertSame('1', $document['data']['id']);
        self::assertArrayHasKey('title', $document['data']['attributes']);
        self::assertArrayHasKey('createdAt', $document['data']['attributes']);
        self::assertSame('http://localhost/api/articles/1', $document['data']['links']['self']);
        self::assertSame('http://localhost/api/articles/1', $document['links']['self']);
        $data = $document['data'];
        self::assertArrayHasKey('relationships', $data);
        $relationships = $data['relationships'];
        self::assertArrayHasKey('author', $relationships);
        /** @var array<string, mixed> $authorRelationship */
        $authorRelationship = $relationships['author'];
        self::assertArrayHasKey('links', $authorRelationship);
        /** @var array<string, string> $relationshipLinks */
        $relationshipLinks = $authorRelationship['links'];
        self::assertArrayHasKey('self', $relationshipLinks);
        self::assertArrayHasKey('related', $relationshipLinks);
        // With linkage_in_resource: 'always', data is always included
        self::assertArrayHasKey('data', $authorRelationship);
    }

    public function testRelationshipLinkageIncludedWhenRequested(): void
    {
        $request = Request::create('/api/articles/1', 'GET', ['include' => 'author']);
        $response = ($this->resourceController())($request, 'articles', '1');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{data: array{relationships: array<string, array<string, mixed>>}} $document */
        $document = $this->decode($response);

        self::assertSame(['type' => 'authors', 'id' => '1'], $document['data']['relationships']['author']['data']);
    }
}
