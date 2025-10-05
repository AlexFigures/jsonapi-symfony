<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Functional;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class IncludeTest extends JsonApiTestCase
{
    public function testIncludeAddsRelatedResources(): void
    {
        $request = Request::create('/api/articles/1', 'GET', ['include' => 'author,tags']);
        $response = ($this->resourceController())($request, 'articles', '1');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('included', $document);
        self::assertCount(3, $document['included']);

        $types = array_column($document['included'], 'type');
        self::assertContains('authors', $types);
        self::assertContains('tags', $types);
    }
}
