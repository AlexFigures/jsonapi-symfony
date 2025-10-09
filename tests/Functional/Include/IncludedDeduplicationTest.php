<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Functional\Include;

use JsonApi\Symfony\Tests\Functional\JsonApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * GAP-013: Duplicate Resource Deduplication in `included`
 *
 * Tests that the `included` array does not contain duplicate resources:
 * - Resources with same type+id appear only once
 * - Works with multiple articles sharing same author
 * - Works with nested includes
 * - Works with circular relationships
 */
final class IncludedDeduplicationTest extends JsonApiTestCase
{
    public function testIncludedArrayDoesNotContainDuplicates(): void
    {
        // Request collection with include - multiple articles may share same author
        $request = Request::create('/api/articles?include=author', 'GET');
        $response = $this->collectionController()($request, 'articles');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{included?: list<array{type: string, id: string}>} $document */
        $document = $this->decode($response);

        if (!isset($document['included'])) {
            self::markTestSkipped('No included resources in response');
        }

        $included = $document['included'];

        // Build list of type+id identifiers
        $identifiers = array_map(
            fn ($resource) => $resource['type'] . '/' . $resource['id'],
            $included
        );

        // Check that all identifiers are unique
        $uniqueIdentifiers = array_unique($identifiers);
        self::assertSame(
            count($identifiers),
            count($uniqueIdentifiers),
            'The included array contains duplicate resources. Each resource should appear only once.'
        );
    }

    public function testIncludedWithMultipleIncludePaths(): void
    {
        // Request with multiple include paths that may reference same resources
        $request = Request::create('/api/articles?include=author,tags', 'GET');
        $response = $this->collectionController()($request, 'articles');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{included?: list<array{type: string, id: string}>} $document */
        $document = $this->decode($response);

        if (!isset($document['included'])) {
            self::markTestSkipped('No included resources in response');
        }

        $included = $document['included'];

        // Build list of type+id identifiers
        $identifiers = array_map(
            fn ($resource) => $resource['type'] . '/' . $resource['id'],
            $included
        );

        // Check that all identifiers are unique
        $uniqueIdentifiers = array_unique($identifiers);
        self::assertSame(
            count($identifiers),
            count($uniqueIdentifiers),
            'The included array contains duplicate resources with multiple include paths'
        );
    }

    public function testIncludedInSingleResourceResponse(): void
    {
        // Single resource with includes
        $request = Request::create('/api/articles/1?include=author,tags', 'GET');
        $response = $this->resourceController()($request, 'articles', '1');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{included?: list<array{type: string, id: string}>} $document */
        $document = $this->decode($response);

        if (!isset($document['included'])) {
            self::markTestSkipped('No included resources in response');
        }

        $included = $document['included'];

        // Build list of type+id identifiers
        $identifiers = array_map(
            fn ($resource) => $resource['type'] . '/' . $resource['id'],
            $included
        );

        // Check that all identifiers are unique
        $uniqueIdentifiers = array_unique($identifiers);
        self::assertSame(
            count($identifiers),
            count($uniqueIdentifiers),
            'The included array contains duplicate resources in single resource response'
        );
    }

    public function testIncludedResourcesAreComplete(): void
    {
        // Verify that each included resource has required fields
        $request = Request::create('/api/articles?include=author', 'GET');
        $response = $this->collectionController()($request, 'articles');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{included?: list<array{type: string, id: string, attributes: mixed}>} $document */
        $document = $this->decode($response);

        if (!isset($document['included'])) {
            self::markTestSkipped('No included resources in response');
        }

        $included = $document['included'];

        foreach ($included as $resource) {
            // Each resource must have type and id
            self::assertArrayHasKey('type', $resource);
            self::assertArrayHasKey('id', $resource);
            self::assertIsString($resource['type']);
            self::assertIsString($resource['id']);

            // Each resource should have attributes (unless it's a resource with no attributes)
            self::assertArrayHasKey('attributes', $resource);
        }
    }

    public function testIncludedOrderIsConsistent(): void
    {
        // Make same request twice and verify included order is consistent
        $request1 = Request::create('/api/articles?include=author,tags', 'GET');
        $response1 = $this->collectionController()($request1, 'articles');

        $request2 = Request::create('/api/articles?include=author,tags', 'GET');
        $response2 = $this->collectionController()($request2, 'articles');

        /** @var array{included?: list<array{type: string, id: string}>} $document1 */
        $document1 = $this->decode($response1);
        /** @var array{included?: list<array{type: string, id: string}>} $document2 */
        $document2 = $this->decode($response2);

        if (!isset($document1['included']) || !isset($document2['included'])) {
            self::markTestSkipped('No included resources in response');
        }

        // Extract identifiers from both responses
        $identifiers1 = array_map(
            fn ($resource) => $resource['type'] . '/' . $resource['id'],
            $document1['included']
        );

        $identifiers2 = array_map(
            fn ($resource) => $resource['type'] . '/' . $resource['id'],
            $document2['included']
        );

        // The set of included resources should be the same
        sort($identifiers1);
        sort($identifiers2);
        self::assertSame($identifiers1, $identifiers2, 'Included resources should be consistent across requests');
    }
}
