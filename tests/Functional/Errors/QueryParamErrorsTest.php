<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Functional\Errors;

use JsonApi\Symfony\Tests\Functional\JsonApiTestCase;
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
}
