<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\JsonApiStatus;

use AlexFigures\Symfony\Http\Exception\NotFoundException;
use AlexFigures\Symfony\Tests\Functional\JsonApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

final class ErrorObjectStatusTest extends JsonApiTestCase
{
    public function testErrorResponsesContainErrorsArray(): void
    {
        $request = Request::create('/api/articles/' . __FUNCTION__, 'GET');

        $errors = $this->captureErrors($request, function () use ($request): void {
            throw new NotFoundException('Resource missing for error audit.');
        }, 404);

        self::assertArrayHasKey('status', $errors[0]);
        self::assertSame('404', $errors[0]['status']);
    }

    public function testErrorObjectsExposeStatusAsString(): void
    {
        $request = Request::create('/api/articles', 'GET', ['include' => 'unknown']);

        $errors = $this->captureErrors($request, fn () => ($this->collectionController())($request, 'articles'), 400);

        foreach ($errors as $error) {
            self::assertIsString($error['status'] ?? null);
        }
    }

    public function testConfiguredErrorAboutLinkIsSerialized(): void
    {
        $error = (new \AlexFigures\Symfony\Http\Error\ErrorBuilder(true))->create('404', 'not-found', aboutLink: 'https://example.org/problems/missing-resource');
        $request = Request::create('/api/articles/missing');
        $errors = $this->captureErrors($request, static function () use ($error): void {
            throw new NotFoundException('Missing resource.', [$error]);
        }, 404);
        self::assertSame('https://example.org/problems/missing-resource', $errors[0]['links']['about']);
    }

    public function testErrorTypeLinkIsNotImplemented(): void
    {
        self::markTestSkipped('ErrorObject does not yet expose the optional links.type member.');
    }

    /**
     * @param callable(): mixed $callback
     *
     * @return list<array<string, mixed>>
     */
    private function captureErrors(Request $request, callable $callback, int $expectedStatus): array
    {
        try {
            $callback();
            self::fail('Expected JSON:API error response.');
        } catch (Throwable $exception) {
            $response = $this->handleException($request, $exception);
            self::assertSame($expectedStatus, $response->getStatusCode());

            $document = $this->decode($response);

            /** @var list<array<string, mixed>> $errors */
            $errors = $document['errors'] ?? [];

            self::assertNotEmpty($errors);

            return $errors;
        }
    }
}
