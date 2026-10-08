<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Http\Controller;

use AlexFigures\JsonApi\Contract\Data\ResourceProcessor;
use AlexFigures\JsonApi\Contract\Tx\TransactionManager;
use AlexFigures\JsonApi\Events\ResourceChangedEvent;
use AlexFigures\JsonApi\Http\Controller\Support\JsonApiResponseFactory;
use AlexFigures\JsonApi\Http\Controller\Support\OperationValidator;
use AlexFigures\JsonApi\Http\Controller\Support\RequestDecoder;
use AlexFigures\JsonApi\Http\Document\DocumentBuilder;
use AlexFigures\JsonApi\Http\Exception\NotFoundException;
use AlexFigures\JsonApi\Http\Exception\UnprocessableEntityException;
use AlexFigures\JsonApi\Http\Exception\ValidationException;
use AlexFigures\JsonApi\Http\Validation\ConstraintViolationMapper;
use AlexFigures\JsonApi\Http\Write\ChangeSetFactory;
use AlexFigures\JsonApi\Http\Write\InputDocumentValidator;
use AlexFigures\JsonApi\Query\Criteria;
use AlexFigures\JsonApi\Resource\Definition\ResourceOperation;
use AlexFigures\JsonApi\Resource\Registry\ResourceRegistryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(path: '/api/{type}/{id}', methods: ['PATCH'], name: 'jsonapi.update')]
/** @internal */
final readonly class UpdateResourceController
{
    public function __construct(
        private ResourceRegistryInterface $registry,
        private OperationValidator $operationValidator,
        private RequestDecoder $requestDecoder,
        private JsonApiResponseFactory $responseFactory,
        private InputDocumentValidator $validator,
        private ChangeSetFactory $changes,
        private ResourceProcessor $processor,
        private TransactionManager $transaction,
        private DocumentBuilder $document,
        private ConstraintViolationMapper $violationMapper,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(Request $request, string $type, string $id): Response
    {
        if (!$this->registry->hasType($type)) {
            throw new NotFoundException(sprintf('Resource type "%s" not found.', $type));
        }

        // Check if UPDATE operation is allowed
        $metadata = $this->registry->getByType($type);
        $this->operationValidator->assertAllowed(ResourceOperation::UPDATE, $metadata->allowedOperations);

        $payload = $this->requestDecoder->decode($request);
        $input = $this->validator->validateAndExtract($type, $id, $payload, 'PATCH');

        try {
            $model = \AlexFigures\JsonApi\Tx\TransactionScope::write($this->transaction, $this->registry, $type, function () use ($type, $id, $input) {
                // Create ChangeSet with both attributes and relationships
                // The processor will handle applying both before validation
                $changes = $this->changes->fromInput(
                    $type,
                    $input['attributes'],
                    $input['relationships']
                );

                // Process entity update (validation + changes, flush handled by WriteListener)
                $entity = $this->processor->processUpdate($type, $id, $changes);

                return $entity;
            });
        } catch (ValidationException $exception) {
            // Denormalization errors (e.g., invalid enum values, type mismatches)
            // are already mapped to JSON:API errors by ConstraintViolationMapper
            throw new UnprocessableEntityException($exception->getMessage(), $exception->getErrors(), previous: $exception);
        } catch (ValidationFailedException $exception) {
            $errors = $this->violationMapper->map($type, $exception->getViolations());

            throw new UnprocessableEntityException('Validation failed.', $errors, previous: $exception);
        }

        // Dispatch event after successful update
        $this->eventDispatcher->dispatch(
            new ResourceChangedEvent($type, $id, 'update')
        );

        $request->attributes->set('_jsonapi_representation_flushed', true);
        $document = $this->document->buildResource($type, $model, new Criteria(), $request);

        return $this->responseFactory->create($document, Response::HTTP_OK, $request->isMethod('HEAD'));
    }

}
