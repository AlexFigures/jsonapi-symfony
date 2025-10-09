<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Functional\Query;

use JsonApi\Symfony\Tests\Functional\JsonApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * GAP-007: Sparse Fieldsets Edge Cases
 *
 * Tests edge cases for sparse fieldsets (fields parameter):
 * - fields work for included resources
 * - empty fields value is handled correctly
 * - fields for multiple types simultaneously
 * - id is always included even if not specified in fields
 */
final class SparseFieldsetsTest extends JsonApiTestCase
{
    public function testFieldsForIncludedResources(): void
    {
        // Request article with author included, but only author's name field
        $request = Request::create(
            '/api/articles/1?include=author&fields[authors]=name',
            'GET'
        );
        $response = $this->resourceController()($request, 'articles', '1');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{data: array, included: list<array{type: string, attributes: array<string, mixed>}>} $document */
        $document = $this->decode($response);

        // Verify included author has only name attribute
        self::assertArrayHasKey('included', $document);
        self::assertCount(1, $document['included']);

        $includedAuthor = $document['included'][0];
        self::assertSame('authors', $includedAuthor['type']);
        self::assertArrayHasKey('attributes', $includedAuthor);

        // Only 'name' should be present in attributes
        self::assertSame(['name'], array_keys($includedAuthor['attributes']));
    }

    public function testFieldsWithEmptyValue(): void
    {
        // Empty fields value should return no attributes (only type, id, links)
        $request = Request::create('/api/articles/1?fields[articles]=', 'GET');
        $response = $this->resourceController()($request, 'articles', '1');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{data: array{type: string, id: string, attributes: array<string, mixed>}} $document */
        $document = $this->decode($response);

        // Should have type, id, links but empty attributes
        self::assertSame('articles', $document['data']['type']);
        self::assertSame('1', $document['data']['id']);
        self::assertArrayHasKey('attributes', $document['data']);
        self::assertEmpty($document['data']['attributes']);
    }

    public function testFieldsForMultipleTypes(): void
    {
        // Request with fields for both primary and included resource types
        $request = Request::create(
            '/api/articles/1?include=author,tags&fields[articles]=title&fields[authors]=name&fields[tags]=name',
            'GET'
        );
        $response = $this->resourceController()($request, 'articles', '1');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{data: array{attributes: array<string, mixed>}, included: list<array{type: string, attributes: array<string, mixed>}>} $document */
        $document = $this->decode($response);

        // Primary resource (article) should have only title
        self::assertSame(['title'], array_keys($document['data']['attributes']));

        // Included resources should respect their fields
        self::assertArrayHasKey('included', $document);

        foreach ($document['included'] as $resource) {
            if ($resource['type'] === 'authors') {
                self::assertSame(['name'], array_keys($resource['attributes']));
            } elseif ($resource['type'] === 'tags') {
                self::assertSame(['name'], array_keys($resource['attributes']));
            }
        }
    }

    public function testIdAlwaysIncluded(): void
    {
        // Even when fields is specified, id should always be present
        $request = Request::create('/api/articles/1?fields[articles]=title', 'GET');
        $response = $this->resourceController()($request, 'articles', '1');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{data: array{type: string, id: string, attributes: array<string, mixed>}} $document */
        $document = $this->decode($response);

        // id must always be present
        self::assertArrayHasKey('id', $document['data']);
        self::assertSame('1', $document['data']['id']);

        // type must always be present
        self::assertArrayHasKey('type', $document['data']);
        self::assertSame('articles', $document['data']['type']);

        // Only title in attributes
        self::assertSame(['title'], array_keys($document['data']['attributes']));
    }

    public function testTypeAlwaysIncluded(): void
    {
        // type should always be present regardless of fields
        $request = Request::create('/api/articles/1?fields[articles]=', 'GET');
        $response = $this->resourceController()($request, 'articles', '1');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{data: array{type: string, id: string}} $document */
        $document = $this->decode($response);

        // type and id must always be present
        self::assertArrayHasKey('type', $document['data']);
        self::assertArrayHasKey('id', $document['data']);
        self::assertSame('articles', $document['data']['type']);
        self::assertSame('1', $document['data']['id']);
    }

    public function testFieldsWithRelationships(): void
    {
        // When fields includes a relationship name, it should be present
        $request = Request::create('/api/articles/1?fields[articles]=title,author', 'GET');
        $response = $this->resourceController()($request, 'articles', '1');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{data: array{attributes: array<string, mixed>, relationships: array<string, mixed>}} $document */
        $document = $this->decode($response);

        // Should have only title attribute
        self::assertSame(['title'], array_keys($document['data']['attributes']));

        // Should have author relationship
        self::assertArrayHasKey('relationships', $document['data']);
        self::assertArrayHasKey('author', $document['data']['relationships']);

        // Should NOT have tags relationship (not in fields)
        self::assertArrayNotHasKey('tags', $document['data']['relationships']);
    }

    public function testFieldsInCollectionResponse(): void
    {
        // Sparse fieldsets should work in collection responses
        $request = Request::create('/api/articles?fields[articles]=title', 'GET');
        $response = $this->collectionController()($request, 'articles');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{data: list<array{attributes: array<string, mixed>}>} $document */
        $document = $this->decode($response);

        self::assertIsArray($document['data']);
        self::assertNotEmpty($document['data']);

        foreach ($document['data'] as $resource) {
            // Each resource should have only title attribute
            self::assertSame(['title'], array_keys($resource['attributes']));
        }
    }

    public function testFieldsWithIncludeInCollection(): void
    {
        // Sparse fieldsets for both primary and included in collection
        $request = Request::create(
            '/api/articles?include=author&fields[articles]=title&fields[authors]=name',
            'GET'
        );
        $response = $this->collectionController()($request, 'articles');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{data: list<array{attributes: array<string, mixed>>>, included: list<array{type: string, attributes: array<string, mixed>}>} $document */
        $document = $this->decode($response);

        // Primary resources should have only title
        foreach ($document['data'] as $resource) {
            self::assertSame(['title'], array_keys($resource['attributes']));
        }

        // Included authors should have only name
        if (isset($document['included'])) {
            foreach ($document['included'] as $resource) {
                if ($resource['type'] === 'authors') {
                    self::assertSame(['name'], array_keys($resource['attributes']));
                }
            }
        }
    }
}
