<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\JsonApiStatus;

use AlexFigures\Symfony\Tests\Functional\JsonApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

final class QueryParameterStatusTest extends JsonApiTestCase
{
    public function testIncludeUnsupportedReturns400(): void
    {
        $request = Request::create('/api/articles', 'GET', ['include' => 'unknown']);

        $errors = $this->captureErrors($request, fn () => ($this->collectionController())($request, 'articles'));

        self::assertSame('invalid-parameter', $errors[0]['code']);
        $this->assertErrorParameter($errors[0], 'include');
    }

    public function testIncludeUnknownPathReturns400(): void
    {
        $request = Request::create('/api/articles', 'GET', ['include' => 'author.unknown']);

        $errors = $this->captureErrors($request, fn () => ($this->collectionController())($request, 'articles'));

        self::assertSame('invalid-parameter', $errors[0]['code']);
        $this->assertErrorParameter($errors[0], 'include');
    }

    public function testSortUnsupportedFieldReturns400(): void
    {
        $request = Request::create('/api/articles', 'GET', ['sort' => '-unsupported']);

        $errors = $this->captureErrors($request, fn () => ($this->collectionController())($request, 'articles'));

        self::assertSame('sort-field-not-allowed', $errors[0]['code']);
        $this->assertErrorParameter($errors[0], 'sort');
    }

    public function testUnknownQueryParameterReturns400(): void
    {
        $request = Request::create('/api/articles', 'GET', ['unexpected' => 'value']);

        $errors = $this->captureErrors($request, fn () => ($this->collectionController())($request, 'articles'));

        self::assertSame('unknown-parameter', $errors[0]['code']);
        $this->assertErrorParameter($errors[0], 'unexpected');
    }

    /**
     * @param callable(): mixed $callback
     *
     * @return list<array<string, mixed>>
     */
    private function captureErrors(Request $request, callable $callback): array
    {
        try {
            $callback();
            self::fail('Expected JSON:API error response.');
        } catch (Throwable $exception) {
            $response = $this->handleException($request, $exception);
            self::assertSame(400, $response->getStatusCode());

            $document = $this->decode($response);

            /** @var list<array<string, mixed>> $errors */
            $errors = $document['errors'] ?? [];

            self::assertNotEmpty($errors);

            return $errors;
        }
    }
}
