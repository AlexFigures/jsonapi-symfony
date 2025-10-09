<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Functional\Errors;

use JsonApi\Symfony\Contract\Data\ChangeSet;
use JsonApi\Symfony\Contract\Data\ResourcePersister;
use JsonApi\Symfony\Http\Controller\CreateResourceController;
use JsonApi\Symfony\Http\Write\InputDocumentValidator;
use JsonApi\Symfony\Http\Write\WriteConfig;
use JsonApi\Symfony\Tests\Functional\JsonApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Throwable;

/**
 * GAP-014: Multiple Validation Errors in Single Response
 *
 * Tests that the server can return multiple validation errors in a single response:
 * - Multiple attribute errors
 * - Multiple relationship errors
 * - Mix of attribute and relationship errors
 * - Each error has correct source pointer
 */
final class MultipleValidationErrorsTest extends JsonApiTestCase
{
    public function testMultipleAttributeValidationErrors(): void
    {
        // This test verifies that multiple validation errors for different attributes
        // are returned in a single response
        $violations = new ConstraintViolationList([
            new ConstraintViolation('Title must not be blank.', null, [], null, 'title', ''),
            new ConstraintViolation('ID is invalid.', null, [], null, 'id', 'invalid-id'),
        ]);

        $controller = $this->createControllerWithValidator(new class ($violations) implements ResourcePersister {
            public function __construct(private ConstraintViolationList $violations)
            {
            }

            public function create(string $type, ChangeSet $changes, ?string $clientId = null): object
            {
                throw new ValidationFailedException('resource', $this->violations);
            }

            public function update(string $type, string $id, ChangeSet $changes): object
            {
                throw new ValidationFailedException('resource', $this->violations);
            }

            public function delete(string $type, string $id): void
            {
            }
        });

        $payload = json_encode([
            'data' => [
                'type' => 'articles',
                'id' => 'article-1',
                'attributes' => [
                    'title' => 'Valid Title',
                ],
                'relationships' => [
                    'author' => ['data' => ['type' => 'authors', 'id' => '1']],
                ],
            ],
        ], \JSON_THROW_ON_ERROR);

        $request = Request::create(
            '/api/articles',
            'POST',
            server: ['CONTENT_TYPE' => 'application/vnd.api+json'],
            content: $payload,
        );

        try {
            $controller($request, 'articles');
            self::fail('Expected exception to be thrown.');
        } catch (Throwable $exception) {
            $response = $this->handleException($request, $exception);
        }

        $errors = $this->assertErrors($response, 422);

        // Should have at least 2 errors
        self::assertGreaterThanOrEqual(2, count($errors));

        // Extract pointers
        $pointers = array_map(fn ($error) => $error['source']['pointer'] ?? null, $errors);

        // Should contain errors for both title and id
        self::assertContains('/data/attributes/title', $pointers);
        self::assertContains('/data/attributes/id', $pointers);
    }

    public function testMultipleRelationshipValidationErrors(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation('Author is required.', null, [], null, 'author', null),
            new ConstraintViolation('At least one tag is required.', null, [], null, 'tags', []),
        ]);

        $controller = $this->createControllerWithValidator(new class ($violations) implements ResourcePersister {
            public function __construct(private ConstraintViolationList $violations)
            {
            }

            public function create(string $type, ChangeSet $changes, ?string $clientId = null): object
            {
                throw new ValidationFailedException('resource', $this->violations);
            }

            public function update(string $type, string $id, ChangeSet $changes): object
            {
                throw new ValidationFailedException('resource', $this->violations);
            }

            public function delete(string $type, string $id): void
            {
            }
        });

        $payload = json_encode([
            'data' => [
                'type' => 'articles',
                'attributes' => ['title' => 'Test'],
                'relationships' => [
                    'author' => ['data' => null],
                    'tags' => ['data' => []],
                ],
            ],
        ], \JSON_THROW_ON_ERROR);

        $request = Request::create(
            '/api/articles',
            'POST',
            server: ['CONTENT_TYPE' => 'application/vnd.api+json'],
            content: $payload,
        );

        try {
            $controller($request, 'articles');
            self::fail('Expected exception to be thrown.');
        } catch (Throwable $exception) {
            $response = $this->handleException($request, $exception);
        }

        $errors = $this->assertErrors($response, 422);

        // Should have at least 2 errors
        self::assertGreaterThanOrEqual(2, count($errors));

        // Extract pointers
        $pointers = array_map(fn ($error) => $error['source']['pointer'] ?? null, $errors);

        // Should contain errors for both relationships
        self::assertContains('/data/relationships/author/data', $pointers);
        self::assertContains('/data/relationships/tags/data', $pointers);
    }

    public function testMixedAttributeAndRelationshipErrors(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation('Title must not be blank.', null, [], null, 'title', ''),
            new ConstraintViolation('Author is required.', null, [], null, 'author', null),
            new ConstraintViolation('Tags must not be empty.', null, [], null, 'tags', []),
        ]);

        $controller = $this->createControllerWithValidator(new class ($violations) implements ResourcePersister {
            public function __construct(private ConstraintViolationList $violations)
            {
            }

            public function create(string $type, ChangeSet $changes, ?string $clientId = null): object
            {
                throw new ValidationFailedException('resource', $this->violations);
            }

            public function update(string $type, string $id, ChangeSet $changes): object
            {
                throw new ValidationFailedException('resource', $this->violations);
            }

            public function delete(string $type, string $id): void
            {
            }
        });

        $payload = json_encode([
            'data' => [
                'type' => 'articles',
                'attributes' => [
                    'title' => 'Valid Title',
                ],
                'relationships' => [
                    'author' => ['data' => ['type' => 'authors', 'id' => '1']],
                    'tags' => ['data' => []],
                ],
            ],
        ], \JSON_THROW_ON_ERROR);

        $request = Request::create(
            '/api/articles',
            'POST',
            server: ['CONTENT_TYPE' => 'application/vnd.api+json'],
            content: $payload,
        );

        try {
            $controller($request, 'articles');
            self::fail('Expected exception to be thrown.');
        } catch (Throwable $exception) {
            $response = $this->handleException($request, $exception);
        }

        $errors = $this->assertErrors($response, 422);

        // Should have at least 3 errors
        self::assertGreaterThanOrEqual(3, count($errors));

        // Extract pointers
        $pointers = array_map(fn ($error) => $error['source']['pointer'] ?? null, $errors);

        // Should contain errors for attributes and relationships
        self::assertContains('/data/attributes/title', $pointers);
        self::assertContains('/data/relationships/author/data', $pointers);
        self::assertContains('/data/relationships/tags/data', $pointers);
    }

    public function testEachErrorHasUniquePointer(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation('Title must not be blank.', null, [], null, 'title', ''),
            new ConstraintViolation('Body is required.', null, [], null, 'body', null),
            new ConstraintViolation('Author is required.', null, [], null, 'author', null),
        ]);

        $controller = $this->createControllerWithValidator(new class ($violations) implements ResourcePersister {
            public function __construct(private ConstraintViolationList $violations)
            {
            }

            public function create(string $type, ChangeSet $changes, ?string $clientId = null): object
            {
                throw new ValidationFailedException('resource', $this->violations);
            }

            public function update(string $type, string $id, ChangeSet $changes): object
            {
                throw new ValidationFailedException('resource', $this->violations);
            }

            public function delete(string $type, string $id): void
            {
            }
        });

        $payload = json_encode([
            'data' => [
                'type' => 'articles',
                'attributes' => [
                    'title' => '',
                ],
                'relationships' => [
                    'author' => ['data' => null],
                ],
            ],
        ], \JSON_THROW_ON_ERROR);

        $request = Request::create(
            '/api/articles',
            'POST',
            server: ['CONTENT_TYPE' => 'application/vnd.api+json'],
            content: $payload,
        );

        try {
            $controller($request, 'articles');
            self::fail('Expected exception to be thrown.');
        } catch (Throwable $exception) {
            $response = $this->handleException($request, $exception);
        }

        $errors = $this->assertErrors($response, 422);

        // Extract pointers
        $pointers = array_map(fn ($error) => $error['source']['pointer'] ?? null, $errors);

        // All pointers should be unique
        $uniquePointers = array_unique($pointers);
        self::assertSame(
            count($pointers),
            count($uniquePointers),
            'Each error should have a unique pointer'
        );
    }

    private function createControllerWithValidator(ResourcePersister $persister): CreateResourceController
    {
        // Allow client-generated IDs for articles type
        $writeConfig = new WriteConfig(true, ['articles' => true]);
        $validator = new InputDocumentValidator($this->registry(), $writeConfig, $this->errorMapper());

        return new CreateResourceController(
            $this->registry(),
            $validator,
            $this->changeSetFactory(),
            $persister,
            $this->transactionManager(),
            $this->documentBuilder(),
            $this->linkGenerator(),
            $writeConfig,
            $this->errorMapper(),
            $this->violationMapper(),
            $this->eventDispatcher(),
            $this->relationshipResolver(),
        );
    }
}
