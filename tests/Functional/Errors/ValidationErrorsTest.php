<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Functional\Errors;

use AlexFigures\JsonApi\Contract\Data\ChangeSet;
use AlexFigures\JsonApi\Contract\Data\ResourceProcessor;
use AlexFigures\JsonApi\Http\Controller\CreateResourceController;
use AlexFigures\JsonApi\Http\Controller\Support\JsonApiResponseFactory;
use AlexFigures\JsonApi\Http\Controller\Support\OperationValidator;
use AlexFigures\JsonApi\Http\Controller\Support\RequestDecoder;
use AlexFigures\JsonApi\Http\Write\InputDocumentValidator;
use AlexFigures\JsonApi\Http\Write\WriteConfig;
use AlexFigures\JsonApi\Tests\Functional\JsonApiTestCase;
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

        $controller = $this->createControllerWithValidator(new class ($violations) implements ResourceProcessor {
            public function __construct(private readonly ConstraintViolationList $violations)
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
                    'author' => ['data' => ['type' => 'authors', 'id' => '1']],
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
        self::assertSame('validation-error', $errors[0]['code']);
        $this->assertErrorPointer($errors[0], '/data/attributes/title');
        $this->assertErrorPointer($errors[1], '/data/relationships/author/data');
        $this->assertErrorPointer($errors[2], '/data/relationships/tags/data/0');
    }

    private function createControllerWithValidator(ResourceProcessor $processor): CreateResourceController
    {
        $baseConfig = $this->writeConfig();
        $writeConfig = new WriteConfig(true, $baseConfig->clientIdAllowed);
        $validator = new InputDocumentValidator($this->registry(), $writeConfig, $this->errorMapper());

        $operationValidator = new OperationValidator($this->errorMapper());
        $requestDecoder = new RequestDecoder($this->errorMapper());
        $responseFactory = new JsonApiResponseFactory();

        return new CreateResourceController(
            $this->registry(),
            $operationValidator,
            $requestDecoder,
            $responseFactory,
            $validator,
            $this->changeSetFactory(),
            $processor,
            $this->transactionManager(),
            $this->documentBuilder(),
            $this->linkGenerator(),
            $writeConfig,
            $this->violationMapper(),
            $this->eventDispatcher(),
        );
    }
}
