<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Functional\Errors;

use JsonApi\Symfony\Tests\Functional\JsonApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

/**
 * GAP-016: Comprehensive Invalid Field Names Validation
 *
 * Tests comprehensive validation of invalid field names in sparse fieldsets.
 * According to JSON:API 1.1 spec, servers SHOULD return 400 Bad Request for invalid field names.
 *
 * This test suite covers:
 * - Reserved field names ('type', 'id') - these are always present and cannot be in sparse fieldsets
 * - Invalid characters in field names
 * - Empty field names
 * - Malformed field parameter syntax
 * - Edge cases with whitespace and special characters
 *
 * @see https://jsonapi.org/format/1.1/#fetching-sparse-fieldsets
 */
final class InvalidFieldNamesTest extends JsonApiTestCase
{
    /**
     * Test that 'type' cannot be explicitly requested in sparse fieldsets.
     * The 'type' member is always present in resource objects regardless of sparse fieldsets.
     *
     * Note: Current implementation allows 'type' in fields parameter but ignores it.
     * This is acceptable behavior as 'type' is always included anyway.
     */
    public function testReservedFieldNameType(): void
    {
        // Request with 'type' in sparse fieldsets
        $request = Request::create('/api/articles', 'GET', ['fields' => ['articles' => 'type,title']]);

        try {
            $response = ($this->collectionController())($request, 'articles');
        } catch (Throwable $exception) {
            $response = $this->handleException($request, $exception);
        }

        // Current implementation: 'type' is not a valid attribute/relationship name
        // so it should return 400 with 'unknown-field' error
        $errors = $this->assertErrors($response, 400);
        self::assertSame('unknown-field', $errors[0]['code']);
        $this->assertErrorParameter($errors[0], 'fields[articles]');
        self::assertStringContainsString('type', $errors[0]['detail']);
    }

    /**
     * Test that 'id' can be requested in sparse fieldsets when it's exposed as an attribute.
     *
     * Note: Article model has #[Id] #[Attribute] on the id property, which means
     * it's exposed as an attribute and can be requested in sparse fieldsets.
     */
    public function testIdFieldWhenExposedAsAttribute(): void
    {
        // Articles expose 'id' as an attribute, so this should succeed
        $request = Request::create('/api/articles', 'GET', ['fields' => ['articles' => 'id,title']]);

        try {
            $response = ($this->collectionController())($request, 'articles');
        } catch (Throwable $exception) {
            $response = $this->handleException($request, $exception);
        }

        // Should succeed because 'id' is an exposed attribute
        self::assertSame(200, $response->getStatusCode());

        /** @var array{data: list<array{attributes: array<string, mixed>}>} $document */
        $document = $this->decode($response);

        // Should have 'id' and 'title' attributes
        foreach ($document['data'] as $resource) {
            $attributeKeys = array_keys($resource['attributes']);
            sort($attributeKeys);
            self::assertSame(['id', 'title'], $attributeKeys);
        }
    }

    /**
     * Test that field names with invalid characters are rejected.
     * JSON:API spec doesn't explicitly define allowed characters, but field names
     * should match attribute/relationship names which are typically alphanumeric + underscore.
     */
    public function testFieldNameWithSpecialCharacters(): void
    {
        // Field name with special characters that don't exist in schema
        $request = Request::create('/api/articles', 'GET', ['fields' => ['articles' => 'title,field@name']]);

        try {
            ($this->collectionController())($request, 'articles');
            self::fail('Expected exception to be thrown.');
        } catch (Throwable $exception) {
            $response = $this->handleException($request, $exception);
        }

        $errors = $this->assertErrors($response, 400);
        self::assertSame('unknown-field', $errors[0]['code']);
        $this->assertErrorParameter($errors[0], 'fields[articles]');
        self::assertStringContainsString('field@name', $errors[0]['detail']);
    }

    /**
     * Test that field names with spaces are rejected.
     */
    public function testFieldNameWithSpaces(): void
    {
        // Field name with spaces
        $request = Request::create('/api/articles', 'GET', ['fields' => ['articles' => 'title,field name']]);

        try {
            ($this->collectionController())($request, 'articles');
            self::fail('Expected exception to be thrown.');
        } catch (Throwable $exception) {
            $response = $this->handleException($request, $exception);
        }

        $errors = $this->assertErrors($response, 400);
        self::assertSame('unknown-field', $errors[0]['code']);
        $this->assertErrorParameter($errors[0], 'fields[articles]');
        self::assertStringContainsString('field name', $errors[0]['detail']);
    }

    /**
     * Test that empty field names are handled correctly.
     * Empty strings in comma-separated list should be filtered out.
     */
    public function testEmptyFieldNameInList(): void
    {
        // Field list with empty entries (double comma)
        $request = Request::create('/api/articles', 'GET', ['fields' => ['articles' => 'title,,createdAt']]);

        try {
            $response = ($this->collectionController())($request, 'articles');
        } catch (Throwable $exception) {
            $response = $this->handleException($request, $exception);
        }

        // Empty field names should be filtered out, so this should succeed
        self::assertSame(200, $response->getStatusCode());

        /** @var array{data: list<array{attributes: array<string, mixed>}>} $document */
        $document = $this->decode($response);

        // Should have only 'title' and 'createdAt' attributes
        foreach ($document['data'] as $resource) {
            $attributeKeys = array_keys($resource['attributes']);
            sort($attributeKeys);
            self::assertSame(['createdAt', 'title'], $attributeKeys);
        }
    }

    /**
     * Test that completely empty fields value is handled correctly.
     * fields[articles]= (empty string) should result in no attributes being returned.
     */
    public function testCompletelyEmptyFieldsValue(): void
    {
        // Empty fields value
        $request = Request::create('/api/articles', 'GET', ['fields' => ['articles' => '']]);

        try {
            $response = ($this->collectionController())($request, 'articles');
        } catch (Throwable $exception) {
            $response = $this->handleException($request, $exception);
        }

        // Empty fields should result in no attributes (but type and id still present)
        self::assertSame(200, $response->getStatusCode());

        /** @var array{data: list<array{type: string, id: string, attributes?: array<string, mixed>}>} $document */
        $document = $this->decode($response);

        foreach ($document['data'] as $resource) {
            // type and id must always be present
            self::assertArrayHasKey('type', $resource);
            self::assertArrayHasKey('id', $resource);

            // attributes should be empty or not present
            if (isset($resource['attributes'])) {
                self::assertEmpty($resource['attributes']);
            }
        }
    }

    /**
     * Test that fields parameter with non-string value is rejected.
     */
    public function testFieldsParameterNotString(): void
    {
        // fields[articles] is an array instead of string
        $request = Request::create('/api/articles', 'GET', ['fields' => ['articles' => ['title', 'body']]]);

        try {
            ($this->collectionController())($request, 'articles');
            self::fail('Expected exception to be thrown.');
        } catch (Throwable $exception) {
            $response = $this->handleException($request, $exception);
        }

        $errors = $this->assertErrors($response, 400);
        self::assertSame('invalid-parameter', $errors[0]['code']);
        $this->assertErrorParameter($errors[0], 'fields[articles]');
        self::assertStringContainsString('comma separated string', $errors[0]['detail']);
    }

    /**
     * Test that fields parameter with numeric key is rejected.
     */
    public function testFieldsParameterWithNumericKey(): void
    {
        // fields[0] instead of fields[type]
        $request = Request::create('/api/articles', 'GET', ['fields' => [0 => 'title']]);

        try {
            ($this->collectionController())($request, 'articles');
            self::fail('Expected exception to be thrown.');
        } catch (Throwable $exception) {
            $response = $this->handleException($request, $exception);
        }

        $errors = $this->assertErrors($response, 400);
        self::assertSame('invalid-parameter', $errors[0]['code']);
        $this->assertErrorParameter($errors[0], 'fields');
        self::assertStringContainsString('resource types', $errors[0]['detail']);
    }

    /**
     * Test that fields parameter with empty string key is rejected.
     */
    public function testFieldsParameterWithEmptyStringKey(): void
    {
        // fields[''] instead of fields[type]
        $request = Request::create('/api/articles', 'GET', ['fields' => ['' => 'title']]);

        try {
            ($this->collectionController())($request, 'articles');
            self::fail('Expected exception to be thrown.');
        } catch (Throwable $exception) {
            $response = $this->handleException($request, $exception);
        }

        $errors = $this->assertErrors($response, 400);
        self::assertSame('invalid-parameter', $errors[0]['code']);
        $this->assertErrorParameter($errors[0], 'fields');
        self::assertStringContainsString('resource types', $errors[0]['detail']);
    }

    /**
     * Test that fields parameter as non-array is rejected.
     */
    public function testFieldsParameterNotArray(): void
    {
        // fields=title instead of fields[articles]=title
        $request = Request::create('/api/articles?fields=title', 'GET');

        try {
            ($this->collectionController())($request, 'articles');
            self::fail('Expected exception to be thrown.');
        } catch (Throwable $exception) {
            $response = $this->handleException($request, $exception);
        }

        $errors = $this->assertErrors($response, 400);
        self::assertSame('invalid-parameter', $errors[0]['code']);
        $this->assertErrorParameter($errors[0], 'fields');
        self::assertStringContainsString('object keyed by resource type', $errors[0]['detail']);
    }

    /**
     * Test that field names with leading/trailing whitespace are trimmed.
     */
    public function testFieldNamesWithWhitespace(): void
    {
        // Field names with leading/trailing spaces
        $request = Request::create('/api/articles', 'GET', ['fields' => ['articles' => ' title , createdAt ']]);

        try {
            $response = ($this->collectionController())($request, 'articles');
        } catch (Throwable $exception) {
            $response = $this->handleException($request, $exception);
        }

        // Whitespace should be trimmed, so this should succeed
        self::assertSame(200, $response->getStatusCode());

        /** @var array{data: list<array{attributes: array<string, mixed>}>} $document */
        $document = $this->decode($response);

        // Should have 'title' and 'createdAt' attributes (trimmed)
        foreach ($document['data'] as $resource) {
            $attributeKeys = array_keys($resource['attributes']);
            sort($attributeKeys);
            self::assertSame(['createdAt', 'title'], $attributeKeys);
        }
    }

    /**
     * Test that duplicate field names are deduplicated.
     */
    public function testDuplicateFieldNames(): void
    {
        // Duplicate field names in list
        $request = Request::create('/api/articles', 'GET', ['fields' => ['articles' => 'title,createdAt,title']]);

        try {
            $response = ($this->collectionController())($request, 'articles');
        } catch (Throwable $exception) {
            $response = $this->handleException($request, $exception);
        }

        // Duplicates should be deduplicated, so this should succeed
        self::assertSame(200, $response->getStatusCode());

        /** @var array{data: list<array{attributes: array<string, mixed>}>} $document */
        $document = $this->decode($response);

        // Should have 'title' and 'createdAt' attributes (no duplicates)
        foreach ($document['data'] as $resource) {
            $attributeKeys = array_keys($resource['attributes']);
            sort($attributeKeys);
            self::assertSame(['createdAt', 'title'], $attributeKeys);
        }
    }

    /**
     * Test that field names with SQL injection attempts are rejected.
     */
    public function testFieldNameWithSqlInjectionAttempt(): void
    {
        // Field name with SQL injection attempt
        $request = Request::create('/api/articles', 'GET', ['fields' => ['articles' => "title'; DROP TABLE articles--"]]);

        try {
            ($this->collectionController())($request, 'articles');
            self::fail('Expected exception to be thrown.');
        } catch (Throwable $exception) {
            $response = $this->handleException($request, $exception);
        }

        $errors = $this->assertErrors($response, 400);
        self::assertSame('unknown-field', $errors[0]['code']);
        $this->assertErrorParameter($errors[0], 'fields[articles]');
        // The malicious field name should be in the error detail
        self::assertStringContainsString("title'; DROP TABLE articles--", $errors[0]['detail']);
    }

    /**
     * Test that field names with path traversal attempts are rejected.
     */
    public function testFieldNameWithPathTraversal(): void
    {
        // Field name with path traversal attempt
        $request = Request::create('/api/articles', 'GET', ['fields' => ['articles' => '../../../etc/passwd']]);

        try {
            ($this->collectionController())($request, 'articles');
            self::fail('Expected exception to be thrown.');
        } catch (Throwable $exception) {
            $response = $this->handleException($request, $exception);
        }

        $errors = $this->assertErrors($response, 400);
        self::assertSame('unknown-field', $errors[0]['code']);
        $this->assertErrorParameter($errors[0], 'fields[articles]');
        self::assertStringContainsString('../../../etc/passwd', $errors[0]['detail']);
    }
}
