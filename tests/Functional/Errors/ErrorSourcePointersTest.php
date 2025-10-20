<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Functional\Errors;

use AlexFigures\Symfony\Contract\Data\ChangeSet;
use AlexFigures\Symfony\Contract\Data\ResourceProcessor;
use AlexFigures\Symfony\Http\Controller\CreateResourceController;
use AlexFigures\Symfony\Http\Write\InputDocumentValidator;
use AlexFigures\Symfony\Http\Write\WriteConfig;
use AlexFigures\Symfony\Tests\Functional\JsonApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Throwable;

/**
 * GAP-009: Error Source Pointers
 *
 * Tests that errors contain proper source pointers:
 * - Attribute errors have source.pointer
 * - Relationship errors have source.pointer
 * - Query parameter errors have source.parameter
 * - Nested attribute errors have correct pointers
 */
final class ErrorSourcePointersTest extends JsonApiTestCase
{
    public function testAttributeErrorHasPointer(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation('Title must not be blank.', null, [], null, 'title', ''),
        ]);

        $controller = $this->createControllerWithValidator(new class ($violations) implements ResourceProcessor {
            public function __construct(private ConstraintViolationList $violations)
            {
            }

            public function processCreate(string $type, ChangeSet $changes, ?string $clientId = null): object
            {
                throw new ValidationFailedException('resource', $this->violations);
            }

            public function processUpdate(string $type, string $id, ChangeSet $changes): object
            {
                throw new ValidationFailedException('resource', $this->violations);
            }

            public function processDelete(string $type, string $id): void
            {
            }
        });

        $payload = json_encode([
            'data' => [
                'type' => 'articles',
                'attributes' => ['title' => ''],
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
        self::assertCount(1, $errors);

        // Verify error has source.pointer
        self::assertArrayHasKey('source', $errors[0]);
        self::assertArrayHasKey('pointer', $errors[0]['source']);
        self::assertSame('/data/attributes/title', $errors[0]['source']['pointer']);
    }

    public function testRelationshipErrorHasPointer(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation('Author is required.', null, [], null, 'author', null),
        ]);

        $controller = $this->createControllerWithValidator(new class ($violations) implements ResourceProcessor {
            public function __construct(private ConstraintViolationList $violations)
            {
            }

            public function processCreate(string $type, ChangeSet $changes, ?string $clientId = null): object
            {
                throw new ValidationFailedException('resource', $this->violations);
            }

            public function processUpdate(string $type, string $id, ChangeSet $changes): object
            {
                throw new ValidationFailedException('resource', $this->violations);
            }

            public function processDelete(string $type, string $id): void
            {
            }
        });

        $payload = json_encode([
            'data' => [
                'type' => 'articles',
                'attributes' => ['title' => 'Test'],
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
        self::assertCount(1, $errors);

        // Verify error has source.pointer for relationship
        self::assertArrayHasKey('source', $errors[0]);
        self::assertArrayHasKey('pointer', $errors[0]['source']);
        self::assertSame('/data/relationships/author/data', $errors[0]['source']['pointer']);
    }

    public function testQueryParamErrorHasParameter(): void
    {
        $request = Request::create('/api/articles', 'GET', ['fields' => ['articles' => 'unknown']]);

        try {
            ($this->collectionController())($request, 'articles');
            self::fail('Expected exception to be thrown.');
        } catch (Throwable $exception) {
            $response = $this->handleException($request, $exception);
        }

        $errors = $this->assertErrors($response, 400);
        self::assertCount(1, $errors);

        // Verify error has source.parameter
        self::assertArrayHasKey('source', $errors[0]);
        self::assertArrayHasKey('parameter', $errors[0]['source']);
        self::assertSame('fields[articles]', $errors[0]['source']['parameter']);
    }

    public function testNestedAttributePointer(): void
    {
        // Test error in array element (e.g., tags[0])
        $violations = new ConstraintViolationList([
            new ConstraintViolation('Tag is invalid.', null, [], null, 'tags[0]', null),
        ]);

        $controller = $this->createControllerWithValidator(new class ($violations) implements ResourceProcessor {
            public function __construct(private ConstraintViolationList $violations)
            {
            }

            public function processCreate(string $type, ChangeSet $changes, ?string $clientId = null): object
            {
                throw new ValidationFailedException('resource', $this->violations);
            }

            public function processUpdate(string $type, string $id, ChangeSet $changes): object
            {
                throw new ValidationFailedException('resource', $this->violations);
            }

            public function processDelete(string $type, string $id): void
            {
            }
        });

        $payload = json_encode([
            'data' => [
                'type' => 'articles',
                'attributes' => ['title' => 'Test'],
                'relationships' => [
                    'tags' => ['data' => [['type' => 'tags', 'id' => '1']]],
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
        self::assertCount(1, $errors);

        // Verify error has source.pointer with array index
        self::assertArrayHasKey('source', $errors[0]);
        self::assertArrayHasKey('pointer', $errors[0]['source']);
        self::assertSame('/data/relationships/tags/data/0', $errors[0]['source']['pointer']);
    }

    public function testMultipleErrorsHaveDistinctPointers(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation('Title must not be blank.', null, [], null, 'title', ''),
            new ConstraintViolation('Author is required.', null, [], null, 'author', null),
            new ConstraintViolation('First tag is invalid.', null, [], null, 'tags[0]', null),
        ]);

        $controller = $this->createControllerWithValidator(new class ($violations) implements ResourceProcessor {
            public function __construct(private ConstraintViolationList $violations)
            {
            }

            public function processCreate(string $type, ChangeSet $changes, ?string $clientId = null): object
            {
                throw new ValidationFailedException('resource', $this->violations);
            }

            public function processUpdate(string $type, string $id, ChangeSet $changes): object
            {
                throw new ValidationFailedException('resource', $this->violations);
            }

            public function processDelete(string $type, string $id): void
            {
            }
        });

        $payload = json_encode([
            'data' => [
                'type' => 'articles',
                'attributes' => ['title' => ''],
                'relationships' => [
                    'author' => ['data' => null],
                    'tags' => ['data' => [['type' => 'tags', 'id' => '1']]],
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
        self::assertCount(3, $errors);

        // Verify each error has distinct pointer
        $pointers = array_map(fn ($error) => $error['source']['pointer'], $errors);
        self::assertContains('/data/attributes/title', $pointers);
        self::assertContains('/data/relationships/author/data', $pointers);
        self::assertContains('/data/relationships/tags/data/0', $pointers);

        // Verify all pointers are unique
        self::assertCount(3, array_unique($pointers));
    }

    private function createControllerWithValidator(ResourceProcessor $processor): CreateResourceController
    {
        $baseConfig = $this->writeConfig();
        $writeConfig = new WriteConfig(true, $baseConfig->clientIdAllowed);
        $validator = new InputDocumentValidator($this->registry(), $writeConfig, $this->errorMapper());

        return new CreateResourceController(
            $this->registry(),
            $validator,
            $this->changeSetFactory(),
            $processor,
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
