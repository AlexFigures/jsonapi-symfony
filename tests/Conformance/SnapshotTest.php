<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Conformance;

use JsonApi\Symfony\Tests\Functional\JsonApiTestCase;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * GAP-010: Conformance Snapshots
 *
 * Tests that JSON:API document format remains consistent across changes.
 * Snapshots protect against regressions in document structure.
 *
 * Dynamic fields (id, timestamps, links) are normalized before comparison.
 */
final class SnapshotTest extends JsonApiTestCase
{
    use MatchesSnapshots;

    public function testCollectionDocumentMatchesSnapshot(): void
    {
        $request = Request::create('/api/articles', 'GET');
        $response = $this->collectionController()($request, 'articles');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);
        $normalized = $this->normalizeDocument($document);

        $this->assertMatchesJsonSnapshot($normalized);
    }

    public function testResourceDocumentMatchesSnapshot(): void
    {
        $request = Request::create('/api/articles/1', 'GET');
        $response = $this->resourceController()($request, 'articles', '1');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);
        $normalized = $this->normalizeDocument($document);

        $this->assertMatchesJsonSnapshot($normalized);
    }

    public function testRelationshipDocumentMatchesSnapshot(): void
    {
        $request = Request::create('/api/articles/1/relationships/author', 'GET');
        $response = $this->relationshipGetController()($request, 'articles', '1', 'author');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);
        $normalized = $this->normalizeDocument($document);

        $this->assertMatchesJsonSnapshot($normalized);
    }

    public function testErrorDocumentMatchesSnapshot(): void
    {
        // Request non-existent resource to get error document
        $request = Request::create('/api/articles/999999', 'GET');

        try {
            $this->resourceController()($request, 'articles', '999999');
            self::fail('Expected NotFoundException to be thrown');
        } catch (\Throwable $exception) {
            $response = $this->handleException($request, $exception);
        }

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());

        $document = $this->decode($response);
        $normalized = $this->normalizeDocument($document);

        $this->assertMatchesJsonSnapshot($normalized);
    }

    public function testAtomicResultsMatchesSnapshot(): void
    {
        $payload = json_encode([
            'atomic:operations' => [
                [
                    'op' => 'add',
                    'ref' => [
                        'type' => 'articles',
                    ],
                    'data' => [
                        'type' => 'articles',
                        'attributes' => [
                            'title' => 'Snapshot Test Article',
                        ],
                        'relationships' => [
                            'author' => [
                                'data' => ['type' => 'authors', 'id' => '1'],
                            ],
                        ],
                    ],
                ],
            ],
        ], \JSON_THROW_ON_ERROR);

        $request = Request::create(
            '/api/operations',
            'POST',
            server: [
                'CONTENT_TYPE' => 'application/vnd.api+json; ext="https://jsonapi.org/ext/atomic"',
                'HTTP_ACCEPT' => 'application/vnd.api+json; ext="https://jsonapi.org/ext/atomic"',
            ],
            content: $payload,
        );

        $response = $this->atomicController()($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        // Decode atomic response manually (different Content-Type)
        $content = $response->getContent();
        self::assertNotFalse($content);
        $document = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($document);

        $normalized = $this->normalizeDocument($document);

        $this->assertMatchesJsonSnapshot($normalized);
    }

    public function testCollectionWithIncludeMatchesSnapshot(): void
    {
        $request = Request::create('/api/articles?include=author', 'GET');
        $response = $this->collectionController()($request, 'articles');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);
        $normalized = $this->normalizeDocument($document);

        $this->assertMatchesJsonSnapshot($normalized);
    }

    public function testCollectionWithSparseFieldsetsMatchesSnapshot(): void
    {
        $request = Request::create('/api/articles?fields[articles]=title', 'GET');
        $response = $this->collectionController()($request, 'articles');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);
        $normalized = $this->normalizeDocument($document);

        $this->assertMatchesJsonSnapshot($normalized);
    }

    public function testCollectionWithPaginationMatchesSnapshot(): void
    {
        $request = Request::create('/api/articles?page[number]=1&page[size]=2', 'GET');
        $response = $this->collectionController()($request, 'articles');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);
        $normalized = $this->normalizeDocument($document);

        $this->assertMatchesJsonSnapshot($normalized);
    }

    /**
     * Normalize document by replacing dynamic values with placeholders.
     *
     * @param  array<string, mixed> $document
     * @return array<string, mixed>
     */
    private function normalizeDocument(array $document): array
    {
        return $this->normalizeValue($document);
    }

    /**
     * Recursively normalize values in the document.
     *
     * @param  mixed $value
     * @return mixed
     */
    private function normalizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $item) {
                // Normalize IDs to placeholders
                if ($key === 'id' && is_string($item)) {
                    $normalized[$key] = '<ID>';
                }
                // Normalize links to remove dynamic parts
                elseif ($key === 'links' && is_array($item)) {
                    $normalized[$key] = $this->normalizeLinks($item);
                }
                // Normalize timestamps
                elseif ($key === 'createdAt' && is_string($item)) {
                    $normalized[$key] = '<TIMESTAMP>';
                }
                // Recursively normalize nested structures
                else {
                    $normalized[$key] = $this->normalizeValue($item);
                }
            }
            return $normalized;
        }

        return $value;
    }

    /**
     * Normalize links by replacing dynamic parts.
     *
     * @param  array<string, mixed> $links
     * @return array<string, mixed>
     */
    private function normalizeLinks(array $links): array
    {
        $normalized = [];
        foreach ($links as $key => $link) {
            if (is_string($link)) {
                // Replace numeric IDs in URLs with placeholder
                $normalized[$key] = preg_replace('/\/\d+/', '/<ID>', $link);
                // Replace UUIDs in URLs with placeholder
                $normalized[$key] = preg_replace('/\/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/', '/<UUID>', $normalized[$key]);
                // Replace any remaining UUID-like strings
                $normalized[$key] = preg_replace('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/', '<UUID>', $normalized[$key]);
                // Replace page numbers with placeholder
                $normalized[$key] = preg_replace('/page%5Bnumber%5D=\d+/', 'page%5Bnumber%5D=<PAGE>', $normalized[$key]);
            } elseif (is_array($link)) {
                $normalized[$key] = $this->normalizeLinks($link);
            } else {
                $normalized[$key] = $link;
            }
        }
        return $normalized;
    }
}
