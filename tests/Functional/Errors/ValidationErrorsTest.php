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

final class ValidationErrorsTest extends JsonApiTestCase
{
    public function testConstraintViolationsAreConvertedToJsonApiErrors(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation('Title must not be blank.', null, [], null, 'title', ''),
            new ConstraintViolation('Author is invalid.', null, [], null, 'author', null),
            new ConstraintViolation('Tag is invalid.', null, [], null, 'tags[0]', null),
        ]);

        $controller = $this->createControllerWithValidator(new class($violations) implements ResourcePersister {
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
                'attributes' => ['title' => ''],
                'relationships' => [
                    'author' => ['data' => ['type' => 'authors', 'id' => '1']],
                    'tags' => ['data' => [['type' => 'tags', 'id' => '1']]],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

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
        self::assertSame('validation-error', $errors[0]['code']);
        $this->assertErrorPointer($errors[0], '/data/attributes/title');
        $this->assertErrorPointer($errors[1], '/data/relationships/author/data');
        $this->assertErrorPointer($errors[2], '/data/relationships/tags/data/0');
    }

    private function createControllerWithValidator(ResourcePersister $persister): CreateResourceController
    {
        $baseConfig = $this->writeConfig();
        $writeConfig = new WriteConfig(true, $baseConfig->clientIdAllowed);
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
