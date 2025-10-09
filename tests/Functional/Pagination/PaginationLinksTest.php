<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Functional\Pagination;

use JsonApi\Symfony\Tests\Functional\JsonApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * GAP-008: Pagination Links Completeness
 *
 * Tests that pagination links are complete and correct:
 * - first and last links are always present
 * - prev and next links are correct
 * - prev is absent on first page
 * - next is absent on last page
 */
final class PaginationLinksTest extends JsonApiTestCase
{
    public function testFirstLastLinksPresent(): void
    {
        // Request first page
        $request = Request::create('/api/articles?page[number]=1&page[size]=2', 'GET');
        $response = $this->collectionController()($request, 'articles');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{links: array<string, string>} $document */
        $document = $this->decode($response);

        // first and last links must always be present
        self::assertArrayHasKey('links', $document);
        self::assertArrayHasKey('first', $document['links']);
        self::assertArrayHasKey('last', $document['links']);

        // Verify first link points to page 1 (URL-encoded)
        $firstLink = urldecode($document['links']['first']);
        self::assertStringContainsString('page[number]=1', $firstLink);
        self::assertStringContainsString('page[size]=2', $firstLink);

        // Verify last link exists (we don't know exact page number without knowing total)
        self::assertIsString($document['links']['last']);
        $lastLink = urldecode($document['links']['last']);
        self::assertStringContainsString('page[size]=2', $lastLink);
    }

    public function testPrevNextLinksCorrect(): void
    {
        // Request middle page (page 2)
        $request = Request::create('/api/articles?page[number]=2&page[size]=2', 'GET');
        $response = $this->collectionController()($request, 'articles');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{links: array<string, string>, meta: array{total: int, page: int, size: int}} $document */
        $document = $this->decode($response);

        $totalPages = (int) ceil($document['meta']['total'] / $document['meta']['size']);

        // If there are multiple pages, prev and next should be present
        if ($totalPages > 1) {
            self::assertArrayHasKey('prev', $document['links']);
            $prevLink = urldecode($document['links']['prev']);
            self::assertStringContainsString('page[number]=1', $prevLink);
            self::assertStringContainsString('page[size]=2', $prevLink);
        }

        if ($totalPages > 2) {
            self::assertArrayHasKey('next', $document['links']);
            $nextLink = urldecode($document['links']['next']);
            self::assertStringContainsString('page[number]=3', $nextLink);
            self::assertStringContainsString('page[size]=2', $nextLink);
        }
    }

    public function testPrevAbsentOnFirstPage(): void
    {
        // Request first page explicitly
        $request = Request::create('/api/articles?page[number]=1&page[size]=2', 'GET');
        $response = $this->collectionController()($request, 'articles');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{links: array<string, string>} $document */
        $document = $this->decode($response);

        // prev link must NOT be present on first page
        self::assertArrayNotHasKey('prev', $document['links']);

        // first, last, and self must be present
        self::assertArrayHasKey('first', $document['links']);
        self::assertArrayHasKey('last', $document['links']);
        self::assertArrayHasKey('self', $document['links']);
    }

    public function testNextAbsentOnLastPage(): void
    {
        // First, get total to calculate last page
        $request = Request::create('/api/articles?page[size]=2', 'GET');
        $response = $this->collectionController()($request, 'articles');

        /** @var array{meta: array{total: int, size: int}} $document */
        $document = $this->decode($response);

        $totalPages = max(1, (int) ceil($document['meta']['total'] / $document['meta']['size']));

        // Now request the last page
        $request = Request::create("/api/articles?page[number]={$totalPages}&page[size]=2", 'GET');
        $response = $this->collectionController()($request, 'articles');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{links: array<string, string>} $document */
        $document = $this->decode($response);

        // next link must NOT be present on last page
        self::assertArrayNotHasKey('next', $document['links']);

        // first, last, and self must be present
        self::assertArrayHasKey('first', $document['links']);
        self::assertArrayHasKey('last', $document['links']);
        self::assertArrayHasKey('self', $document['links']);

        // prev should be present if there's more than one page
        if ($totalPages > 1) {
            self::assertArrayHasKey('prev', $document['links']);
        }
    }

    public function testPaginationLinksPreserveQueryParams(): void
    {
        // Request with additional query parameters
        $request = Request::create('/api/articles?page[number]=1&page[size]=2&fields[articles]=title&sort=title', 'GET');
        $response = $this->collectionController()($request, 'articles');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{links: array<string, string>} $document */
        $document = $this->decode($response);

        // All pagination links should preserve other query parameters (URL-decoded)
        $firstLink = urldecode($document['links']['first']);
        self::assertStringContainsString('fields[articles]=title', $firstLink);
        self::assertStringContainsString('sort=title', $firstLink);

        $lastLink = urldecode($document['links']['last']);
        self::assertStringContainsString('fields[articles]=title', $lastLink);
        self::assertStringContainsString('sort=title', $lastLink);

        // If next exists, it should also preserve params
        if (isset($document['links']['next'])) {
            $nextLink = urldecode($document['links']['next']);
            self::assertStringContainsString('fields[articles]=title', $nextLink);
            self::assertStringContainsString('sort=title', $nextLink);
        }
    }

    public function testPaginationMetaPresent(): void
    {
        $request = Request::create('/api/articles?page[number]=1&page[size]=2', 'GET');
        $response = $this->collectionController()($request, 'articles');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{meta: array{total: int, page: int, size: int}} $document */
        $document = $this->decode($response);

        // Pagination meta should be present
        self::assertArrayHasKey('meta', $document);
        self::assertArrayHasKey('total', $document['meta']);
        self::assertArrayHasKey('page', $document['meta']);
        self::assertArrayHasKey('size', $document['meta']);

        // Verify values
        self::assertIsInt($document['meta']['total']);
        self::assertSame(1, $document['meta']['page']);
        self::assertSame(2, $document['meta']['size']);
    }

    public function testSinglePageHasNoNextOrPrev(): void
    {
        // Request with page size larger than total items
        $request = Request::create('/api/articles?page[number]=1&page[size]=100', 'GET');
        $response = $this->collectionController()($request, 'articles');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{links: array<string, string>, meta: array{total: int}} $document */
        $document = $this->decode($response);

        // If all items fit on one page, no prev/next links
        if ($document['meta']['total'] <= 100) {
            self::assertArrayNotHasKey('prev', $document['links']);
            self::assertArrayNotHasKey('next', $document['links']);
        }

        // first and last should still be present
        self::assertArrayHasKey('first', $document['links']);
        self::assertArrayHasKey('last', $document['links']);
    }

    public function testPaginationLinksAreValidUrls(): void
    {
        $request = Request::create('/api/articles?page[number]=2&page[size]=2', 'GET');
        $response = $this->collectionController()($request, 'articles');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{links: array<string, string>} $document */
        $document = $this->decode($response);

        // All links should be valid URLs
        foreach (['self', 'first', 'last'] as $linkName) {
            self::assertArrayHasKey($linkName, $document['links']);
            self::assertMatchesRegularExpression(
                '#^https?://.+#',
                $document['links'][$linkName],
                "Link '{$linkName}' must be a valid URL"
            );
        }

        // Check optional links if present
        foreach (['prev', 'next'] as $linkName) {
            if (isset($document['links'][$linkName])) {
                self::assertMatchesRegularExpression(
                    '#^https?://.+#',
                    $document['links'][$linkName],
                    "Link '{$linkName}' must be a valid URL"
                );
            }
        }
    }
}
