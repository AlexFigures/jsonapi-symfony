<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Functional\Errors;

use JsonApi\Symfony\Tests\Functional\JsonApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

final class RelationshipErrorsTest extends JsonApiTestCase
{
    public function testTypeMismatchInToOneRelationship(): void
    {
        $payload = json_encode([
            'data' => ['type' => 'tags', 'id' => 'x'],
        ], JSON_THROW_ON_ERROR);
        $request = Request::create(
            '/api/articles/1/relationships/author',
            'PATCH',
            server: ['CONTENT_TYPE' => 'application/vnd.api+json'],
            content: $payload,
        );

        try {
            ($this->relationshipWriteController())($request, 'articles', '1', 'author');
            self::fail('Expected exception to be thrown.');
        } catch (Throwable $exception) {
            $response = $this->handleException($request, $exception);
        }

        $errors = $this->assertErrors($response, 409);
        self::assertNotEmpty($errors);
        $first = $errors[0] ?? null;
        if (!is_array($first)) {
            self::fail('Error entries must be arrays.');
        }
        /** @var array<string, mixed> $first */
        self::assertSame('type-mismatch', $first['code']);
        $this->assertErrorPointer($first, '/data/type');
    }

    public function testMethodNotAllowedForToOneRelationship(): void
    {
        $payload = json_encode([
            'data' => ['type' => 'authors', 'id' => '1'],
        ], JSON_THROW_ON_ERROR);
        $request = Request::create(
            '/api/articles/1/relationships/author',
            'POST',
            server: ['CONTENT_TYPE' => 'application/vnd.api+json'],
            content: $payload,
        );

        try {
            ($this->relationshipWriteController())($request, 'articles', '1', 'author');
            self::fail('Expected exception to be thrown.');
        } catch (Throwable $exception) {
            $response = $this->handleException($request, $exception);
        }

        $errors = $this->assertErrors($response, 405);
        self::assertNotEmpty($errors);
        $first = $errors[0] ?? null;
        if (!is_array($first)) {
            self::fail('Error entries must be arrays.');
        }
        /** @var array<string, mixed> $first */
        self::assertSame('method-not-allowed', $first['code']);
        self::assertSame('PATCH', $response->headers->get('Allow'));
    }

    public function testResourceNotFoundInToManyRelationship(): void
    {
        $payload = json_encode([
            'data' => [
                ['type' => 'tags', 'id' => '999'],
            ],
        ], JSON_THROW_ON_ERROR);
        $request = Request::create(
            '/api/articles/1/relationships/tags',
            'POST',
            server: ['CONTENT_TYPE' => 'application/vnd.api+json'],
            content: $payload,
        );

        try {
            ($this->relationshipWriteController())($request, 'articles', '1', 'tags');
            self::fail('Expected exception to be thrown.');
        } catch (Throwable $exception) {
            $response = $this->handleException($request, $exception);
        }

        $errors = $this->assertErrors($response, 404);
        self::assertNotEmpty($errors);
        $first = $errors[0] ?? null;
        if (!is_array($first)) {
            self::fail('Error entries must be arrays.');
        }
        /** @var array<string, mixed> $first */
        self::assertSame('resource-not-found', $first['code']);
        $this->assertErrorPointer($first, '/data/0');
        $detail = $first['detail'] ?? null;
        self::assertIsString($detail);
        self::assertStringContainsString('999', $detail);
    }
}
