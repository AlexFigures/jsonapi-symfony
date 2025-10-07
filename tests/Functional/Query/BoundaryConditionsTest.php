<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Functional\Query;

use JsonApi\Symfony\Tests\Functional\JsonApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Tests boundary conditions for pagination parameters.
 * 
 * These tests ensure that edge cases for page[size] and page[number]
 * are properly validated and handled, which helps kill mutation testing
 * escaped mutants related to boundary condition operators (>, >=, <, <=).
 */
final class BoundaryConditionsTest extends JsonApiTestCase
{
    public function testPageSizeExactlyAtMax(): void
    {
        // Default max size is 100 (from PaginationConfig)
        $request = Request::create('/api/articles?page[size]=100', 'GET');
        $response = ($this->collectionController())($request, 'articles');
        
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        
        /** @var array{data: list<array<string, mixed>>, meta: array{size: int}} $document */
        $document = $this->decode($response);
        
        // Should succeed with max size
        self::assertSame(100, $document['meta']['size']);
    }

    public function testPageSizeExceedsMax(): void
    {
        // Try to exceed max size (100)
        $request = Request::create('/api/articles?page[size]=101', 'GET');
        
        try {
            ($this->collectionController())($request, 'articles');
            self::fail('Expected exception to be thrown.');
        } catch (Throwable $exception) {
            $response = $this->handleException($request, $exception);
        }
        
        $errors = $this->assertErrors($response, 400);
        self::assertSame('page-size-too-large', $errors[0]['code']);
        $this->assertErrorParameter($errors[0], 'page[size]');
        self::assertStringContainsString('100', $errors[0]['detail']);
    }

    public function testPageSizeExactlyOne(): void
    {
        // Minimum valid page size is 1
        $request = Request::create('/api/articles?page[size]=1', 'GET');
        $response = ($this->collectionController())($request, 'articles');
        
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        
        /** @var array{data: list<array<string, mixed>>, meta: array{size: int}} $document */
        $document = $this->decode($response);
        
        self::assertSame(1, $document['meta']['size']);
        self::assertCount(1, $document['data']);
    }

    public function testPageSizeZero(): void
    {
        // Page size must be at least 1
        $request = Request::create('/api/articles?page[size]=0', 'GET');
        
        try {
            ($this->collectionController())($request, 'articles');
            self::fail('Expected exception to be thrown.');
        } catch (Throwable $exception) {
            $response = $this->handleException($request, $exception);
        }
        
        $errors = $this->assertErrors($response, 400);
        self::assertSame('invalid-parameter', $errors[0]['code']);
        $this->assertErrorParameter($errors[0], 'page[size]');
        self::assertStringContainsString('greater than or equal to 1', $errors[0]['detail']);
    }

    public function testPageSizeNegative(): void
    {
        // Negative page size is invalid
        $request = Request::create('/api/articles?page[size]=-1', 'GET');
        
        try {
            ($this->collectionController())($request, 'articles');
            self::fail('Expected exception to be thrown.');
        } catch (Throwable $exception) {
            $response = $this->handleException($request, $exception);
        }
        
        $errors = $this->assertErrors($response, 400);
        self::assertSame('invalid-parameter', $errors[0]['code']);
        $this->assertErrorParameter($errors[0], 'page[size]');
    }

    public function testPageNumberExactlyOne(): void
    {
        // Minimum valid page number is 1
        $request = Request::create('/api/articles?page[number]=1', 'GET');
        $response = ($this->collectionController())($request, 'articles');
        
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        
        /** @var array{meta: array{page: int}} $document */
        $document = $this->decode($response);
        
        self::assertSame(1, $document['meta']['page']);
    }

    public function testPageNumberZero(): void
    {
        // Page number must be at least 1
        $request = Request::create('/api/articles?page[number]=0', 'GET');
        
        try {
            ($this->collectionController())($request, 'articles');
            self::fail('Expected exception to be thrown.');
        } catch (Throwable $exception) {
            $response = $this->handleException($request, $exception);
        }
        
        $errors = $this->assertErrors($response, 400);
        self::assertSame('invalid-parameter', $errors[0]['code']);
        $this->assertErrorParameter($errors[0], 'page[number]');
        self::assertStringContainsString('greater than or equal to 1', $errors[0]['detail']);
    }

    public function testPageNumberNegative(): void
    {
        // Negative page number is invalid
        $request = Request::create('/api/articles?page[number]=-1', 'GET');
        
        try {
            ($this->collectionController())($request, 'articles');
            self::fail('Expected exception to be thrown.');
        } catch (Throwable $exception) {
            $response = $this->handleException($request, $exception);
        }
        
        $errors = $this->assertErrors($response, 400);
        self::assertSame('invalid-parameter', $errors[0]['code']);
        $this->assertErrorParameter($errors[0], 'page[number]');
    }

    public function testPageSizeJustBelowMax(): void
    {
        // Test size = max - 1 (99)
        $request = Request::create('/api/articles?page[size]=99', 'GET');
        $response = ($this->collectionController())($request, 'articles');
        
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        
        /** @var array{meta: array{size: int}} $document */
        $document = $this->decode($response);
        
        self::assertSame(99, $document['meta']['size']);
    }

    public function testPageSizeTwo(): void
    {
        // Test size = min + 1 (2)
        $request = Request::create('/api/articles?page[size]=2', 'GET');
        $response = ($this->collectionController())($request, 'articles');
        
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        
        /** @var array{data: list<array<string, mixed>>, meta: array{size: int}} $document */
        $document = $this->decode($response);
        
        self::assertSame(2, $document['meta']['size']);
        self::assertLessThanOrEqual(2, count($document['data']));
    }

    public function testPageNumberTwo(): void
    {
        // Test number = min + 1 (2)
        $request = Request::create('/api/articles?page[number]=2&page[size]=5', 'GET');
        $response = ($this->collectionController())($request, 'articles');
        
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        
        /** @var array{meta: array{page: int}} $document */
        $document = $this->decode($response);
        
        self::assertSame(2, $document['meta']['page']);
    }

    public function testPageSizeVeryLarge(): void
    {
        // Test size = max + 100 (200)
        $request = Request::create('/api/articles?page[size]=200', 'GET');
        
        try {
            ($this->collectionController())($request, 'articles');
            self::fail('Expected exception to be thrown.');
        } catch (Throwable $exception) {
            $response = $this->handleException($request, $exception);
        }
        
        $errors = $this->assertErrors($response, 400);
        self::assertSame('page-size-too-large', $errors[0]['code']);
    }

    public function testPageNumberVeryLarge(): void
    {
        // Test requesting a page number far beyond available data
        $request = Request::create('/api/articles?page[number]=1000&page[size]=10', 'GET');
        $response = ($this->collectionController())($request, 'articles');
        
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        
        /** @var array{data: list<array<string, mixed>>, meta: array{page: int}} $document */
        $document = $this->decode($response);
        
        // Should return empty data but still be valid
        self::assertSame(1000, $document['meta']['page']);
        self::assertSame([], $document['data']);
    }

    public function testCombinedBoundaryConditions(): void
    {
        // Test max size with page number 1
        $request = Request::create('/api/articles?page[number]=1&page[size]=100', 'GET');
        $response = ($this->collectionController())($request, 'articles');
        
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        
        /** @var array{meta: array{page: int, size: int}} $document */
        $document = $this->decode($response);
        
        self::assertSame(1, $document['meta']['page']);
        self::assertSame(100, $document['meta']['size']);
    }

    public function testMinimumValidPagination(): void
    {
        // Test minimum valid values: page[number]=1, page[size]=1
        $request = Request::create('/api/articles?page[number]=1&page[size]=1', 'GET');
        $response = ($this->collectionController())($request, 'articles');
        
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        
        /** @var array{data: list<array<string, mixed>>, meta: array{page: int, size: int}} $document */
        $document = $this->decode($response);
        
        self::assertSame(1, $document['meta']['page']);
        self::assertSame(1, $document['meta']['size']);
        self::assertCount(1, $document['data']);
    }
}

