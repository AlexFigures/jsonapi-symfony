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

        /** @var array{included: list<array<string, mixed>>} $document */
        $document = $this->decode($response);

        $included = $document['included'];
        self::assertCount(3, $included);

        $types = array_column($included, 'type');
        self::assertContains('authors', $types);
        self::assertContains('tags', $types);
    }
}
