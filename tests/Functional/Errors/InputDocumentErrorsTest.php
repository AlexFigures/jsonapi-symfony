<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Functional\Errors;

use JsonApi\Symfony\Tests\Functional\JsonApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

final class InputDocumentErrorsTest extends JsonApiTestCase
{
    public function testTypeMismatch(): void
    {
        $payload = json_encode([
            'data' => [
                'type' => 'authors',
                'attributes' => ['title' => 'Test'],
            ],
        ], \JSON_THROW_ON_ERROR);
        $request = Request::create(
            '/api/articles',
            'POST',
            server: ['CONTENT_TYPE' => 'application/vnd.api+json'],
            content: $payload,
        );

        try {
            ($this->createController())($request, 'articles');
            self::fail('Expected exception to be thrown.');
        } catch (Throwable $exception) {
            $response = $this->handleException($request, $exception);
        }

        $errors = $this->assertErrors($response, 409);
        self::assertSame('type-mismatch', $errors[0]['code']);
        $this->assertErrorPointer($errors[0], '/data/type');
    }

    public function testMissingIdOnPatch(): void
    {
        $payload = json_encode([
            'data' => [
                'type' => 'articles',
                'attributes' => ['title' => 'Updated'],
            ],
        ], \JSON_THROW_ON_ERROR);
        $request = Request::create(
            '/api/articles/1',
            'PATCH',
            server: ['CONTENT_TYPE' => 'application/vnd.api+json'],
            content: $payload,
        );

        try {
            ($this->updateController())($request, 'articles', '1');
            self::fail('Expected exception to be thrown.');
        } catch (Throwable $exception) {
            $response = $this->handleException($request, $exception);
        }

        $errors = $this->assertErrors($response, 409);
        self::assertSame('id-mismatch', $errors[0]['code']);
        $this->assertErrorPointer($errors[0], '/data/id');
    }

    public function testMalformedJson(): void
    {
        $request = Request::create(
            '/api/articles',
            'POST',
            server: ['CONTENT_TYPE' => 'application/vnd.api+json'],
            content: '{',
        );

        try {
            ($this->createController())($request, 'articles');
            self::fail('Expected exception to be thrown.');
        } catch (Throwable $exception) {
            $response = $this->handleException($request, $exception);
        }

        $errors = $this->assertErrors($response, 400);
        self::assertSame('invalid-json', $errors[0]['code']);
        $this->assertErrorPointer($errors[0], '/');
    }
}
