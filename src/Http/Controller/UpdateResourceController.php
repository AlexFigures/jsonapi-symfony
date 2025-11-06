<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Controller;

use AlexFigures\Symfony\Contract\Data\ResourceProcessor;
use AlexFigures\Symfony\Contract\Tx\TransactionManager;
use AlexFigures\Symfony\Events\ResourceChangedEvent;
use AlexFigures\Symfony\Http\Controller\Support\JsonApiResponseFactory;
use AlexFigures\Symfony\Http\Controller\Support\OperationValidator;
use AlexFigures\Symfony\Http\Controller\Support\RequestDecoder;
use AlexFigures\Symfony\Http\Document\DocumentBuilder;
use AlexFigures\Symfony\Http\Exception\NotFoundException;
use AlexFigures\Symfony\Http\Exception\UnprocessableEntityException;
use AlexFigures\Symfony\Http\Exception\ValidationException;
use AlexFigures\Symfony\Http\Validation\ConstraintViolationMapper;
use AlexFigures\Symfony\Http\Write\ChangeSetFactory;
use AlexFigures\Symfony\Http\Write\InputDocumentValidator;
use AlexFigures\Symfony\Query\Criteria;
use AlexFigures\Symfony\Resource\Definition\ResourceOperation;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(path: '/api/{type}/{id}', methods: ['PATCH'], name: 'jsonapi.update')]
final class UpdateResourceController
{
    public function __construct(
        private readonly ResourceRegistryInterface $registry,
        private readonly OperationValidator $operationValidator,
        private readonly RequestDecoder $requestDecoder,
        private readonly JsonApiResponseFactory $responseFactory,
        private readonly InputDocumentValidator $validator,
        private readonly ChangeSetFactory $changes,
        private readonly ResourceProcessor $processor,
        private readonly TransactionManager $transaction,
        private readonly DocumentBuilder $document,
        private readonly ConstraintViolationMapper $violationMapper,
        private readonly EventDispatcherInterface $eventDispatcher,
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
            $model = $this->transaction->transactional(function () use ($type, $id, $input) {
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

        $document = $this->document->buildResource($type, $model, new Criteria(), $request);

        return $this->responseFactory->create($document, Response::HTTP_OK, $request->isMethod('HEAD'));
    }

}
