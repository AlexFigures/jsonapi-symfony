<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Functional\Errors;

use AlexFigures\Symfony\Tests\Functional\JsonApiTestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;

final class InternalErrorTest extends JsonApiTestCase
{
    public function testInternalServerErrorHidesDetailsInProduction(): void
    {
        $request = Request::create('/api/articles', 'GET');
        $response = $this->handleException($request, new RuntimeException('Boom'));

        $errors = $this->assertErrors($response, 500);
        self::assertNotEmpty($errors);
        $first = $errors[0] ?? null;
        if (!is_array($first)) {
            self::fail('Error entries must be arrays.');
        }
        /** @var array<string, mixed> $first */
        self::assertSame('internal-server-error', $first['code']);
        self::assertArrayNotHasKey('meta', $first);
    }

    public function testInternalServerErrorExposesMetaInDebug(): void
    {
        $request = Request::create('/api/articles', 'GET');
        $exception = new RuntimeException('Boom');
        $response = $this->handleException($request, $exception, true);

        $errors = $this->assertErrors($response, 500);
        self::assertNotEmpty($errors);
        $first = $errors[0] ?? null;
        if (!is_array($first)) {
            self::fail('Error entries must be arrays.');
        }
        /** @var array<string, mixed> $first */
        self::assertSame('internal-server-error', $first['code']);
        self::assertArrayHasKey('meta', $first);
        $meta = $first['meta'] ?? null;
        if (!is_array($meta)) {
            self::fail('Error meta must be an array.');
        }
        /** @var array<string, mixed> $meta */
        self::assertSame(RuntimeException::class, $meta['exceptionClass'] ?? null);
        self::assertSame('Boom', $meta['message'] ?? null);
    }
}
