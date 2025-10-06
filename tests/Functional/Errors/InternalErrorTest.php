<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Functional\Errors;

use JsonApi\Symfony\Tests\Functional\JsonApiTestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;

final class InternalErrorTest extends JsonApiTestCase
{
    public function testInternalServerErrorHidesDetailsInProduction(): void
    {
        $request = Request::create('/api/articles', 'GET');
        $response = $this->handleException($request, new RuntimeException('Boom'));

        $errors = $this->assertErrors($response, 500);
        self::assertSame('internal-server-error', $errors[0]['code']);
        self::assertArrayNotHasKey('meta', $errors[0]);
    }

    public function testInternalServerErrorExposesMetaInDebug(): void
    {
        $request = Request::create('/api/articles', 'GET');
        $exception = new RuntimeException('Boom');
        $response = $this->handleException($request, $exception, true);

        $errors = $this->assertErrors($response, 500);
        self::assertSame('internal-server-error', $errors[0]['code']);
        self::assertArrayHasKey('meta', $errors[0]);
        self::assertSame(RuntimeException::class, $errors[0]['meta']['exceptionClass'] ?? null);
        self::assertSame('Boom', $errors[0]['meta']['message'] ?? null);
    }
}
