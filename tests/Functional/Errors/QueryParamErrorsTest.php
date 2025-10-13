<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Functional\Errors;

use AlexFigures\Symfony\Tests\Functional\JsonApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

final class QueryParamErrorsTest extends JsonApiTestCase
{
    public function testUnknownField(): void
    {
        $request = Request::create('/api/articles', 'GET', ['fields' => ['articles' => 'unknown']]);

        try {
            ($this->collectionController())($request, 'articles');
            self::fail('Expected exception to be thrown.');
        } catch (Throwable $exception) {
            $response = $this->handleException($request, $exception);
        }

        $errors = $this->assertErrors($response, 400);
        self::assertSame('unknown-field', $errors[0]['code']);
        $this->assertErrorParameter($errors[0], 'fields[articles]');
    }

    public function testInvalidIncludePath(): void
    {
        $request = Request::create('/api/articles', 'GET', ['include' => 'unknown']);

        try {
            ($this->collectionController())($request, 'articles');
            self::fail('Expected exception to be thrown.');
        } catch (Throwable $exception) {
            $response = $this->handleException($request, $exception);
        }

        $errors = $this->assertErrors($response, 400);
        self::assertSame('invalid-parameter', $errors[0]['code']);
        $this->assertErrorParameter($errors[0], 'include');
    }

    public function testPageSizeTooLarge(): void
    {
        $request = Request::create('/api/articles', 'GET', ['page' => ['size' => 1000]]);

        try {
            ($this->collectionController())($request, 'articles');
            self::fail('Expected exception to be thrown.');
        } catch (Throwable $exception) {
            $response = $this->handleException($request, $exception);
        }

        $errors = $this->assertErrors($response, 400);
        self::assertSame('page-size-too-large', $errors[0]['code']);
        $this->assertErrorParameter($errors[0], 'page[size]');
    }

    public function testSortFieldNotAllowed(): void
    {
        $request = Request::create('/api/articles', 'GET', ['sort' => '-hacker']);

        try {
            ($this->collectionController())($request, 'articles');
            self::fail('Expected exception to be thrown.');
        } catch (Throwable $exception) {
            $response = $this->handleException($request, $exception);
        }

        $errors = $this->assertErrors($response, 400);
        self::assertSame('sort-field-not-allowed', $errors[0]['code']);
        $this->assertErrorParameter($errors[0], 'sort');
    }

    public function testUnknownResourceTypeInFields(): void
    {
        // Test validation of unknown resource type in fields[TYPE]
        $request = Request::create('/api/articles', 'GET', ['fields' => ['unknown-type' => 'id,title']]);

        try {
            ($this->collectionController())($request, 'articles');
            self::fail('Expected exception to be thrown.');
        } catch (Throwable $exception) {
            $response = $this->handleException($request, $exception);
        }

        $errors = $this->assertErrors($response, 400);
        self::assertSame('unknown-type', $errors[0]['code']);
        $this->assertErrorParameter($errors[0], 'fields[unknown-type]');
        self::assertStringContainsString('unknown-type', $errors[0]['detail']);
    }

    public function testNestedInvalidIncludePath(): void
    {
        // Test validation of nested include path with unknown relationship
        $request = Request::create('/api/articles', 'GET', ['include' => 'author.unknown']);

        try {
            ($this->collectionController())($request, 'articles');
            self::fail('Expected exception to be thrown.');
        } catch (Throwable $exception) {
            $response = $this->handleException($request, $exception);
        }

        $errors = $this->assertErrors($response, 400);
        self::assertSame('invalid-parameter', $errors[0]['code']);
        $this->assertErrorParameter($errors[0], 'include');
        self::assertStringContainsString('unknown', $errors[0]['detail']);
    }

    public function testMultipleSortFieldsWithOneInvalid(): void
    {
        // Test that validation catches invalid sort field even when mixed with valid ones
        $request = Request::create('/api/articles', 'GET', ['sort' => 'title,-invalid,createdAt']);

        try {
            ($this->collectionController())($request, 'articles');
            self::fail('Expected exception to be thrown.');
        } catch (Throwable $exception) {
            $response = $this->handleException($request, $exception);
        }

        $errors = $this->assertErrors($response, 400);
        self::assertSame('sort-field-not-allowed', $errors[0]['code']);
        $this->assertErrorParameter($errors[0], 'sort');
        self::assertStringContainsString('invalid', $errors[0]['detail']);
    }

    public function testFieldsForUnknownFieldInKnownType(): void
    {
        // Test validation of unknown field name in known resource type
        $request = Request::create('/api/articles', 'GET', ['fields' => ['articles' => 'title,unknown,id']]);

        try {
            ($this->collectionController())($request, 'articles');
            self::fail('Expected exception to be thrown.');
        } catch (Throwable $exception) {
            $response = $this->handleException($request, $exception);
        }

        $errors = $this->assertErrors($response, 400);
        self::assertSame('unknown-field', $errors[0]['code']);
        $this->assertErrorParameter($errors[0], 'fields[articles]');
        self::assertStringContainsString('unknown', $errors[0]['detail']);
    }
}
