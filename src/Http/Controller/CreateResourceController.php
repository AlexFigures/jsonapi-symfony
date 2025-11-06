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
use AlexFigures\Symfony\Http\Exception\ForbiddenException;
use AlexFigures\Symfony\Http\Exception\NotFoundException;
use AlexFigures\Symfony\Http\Exception\UnprocessableEntityException;
use AlexFigures\Symfony\Http\Exception\ValidationException;
use AlexFigures\Symfony\Http\Link\LinkGenerator;
use AlexFigures\Symfony\Http\Validation\ConstraintViolationMapper;
use AlexFigures\Symfony\Http\Write\ChangeSetFactory;
use AlexFigures\Symfony\Http\Write\InputDocumentValidator;
use AlexFigures\Symfony\Http\Write\WriteConfig;
use AlexFigures\Symfony\Query\Criteria;
use AlexFigures\Symfony\Resource\Definition\ResourceOperation;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(path: '/api/{type}', methods: ['POST'], name: 'jsonapi.create')]
final class CreateResourceController
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
        private readonly LinkGenerator $links,
        private readonly WriteConfig $writeConfig,
        private readonly ConstraintViolationMapper $violationMapper,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(Request $request, string $type): Response
    {
        if (!$this->registry->hasType($type)) {
            throw new NotFoundException(sprintf('Resource type "%s" not found.', $type));
        }

        // Check if CREATE operation is allowed
        $metadata = $this->registry->getByType($type);
        $this->operationValidator->assertAllowed(ResourceOperation::CREATE, $metadata->allowedOperations);

        $payload = $this->requestDecoder->decode($request);
        $input = $this->validator->validateAndExtract($type, null, $payload, 'POST');

        $clientId = $input['id'];
        if ($clientId !== null && !$this->writeConfig->allowClientId($type)) {
            throw new ForbiddenException(sprintf('Client-generated IDs are not allowed for type "%s".', $type));
        }

        try {
            $model = $this->transaction->transactional(function () use ($type, $input) {
                // Create ChangeSet with both attributes and relationships
                // The processor will handle applying both before validation
                $changes = $this->changes->fromInput(
                    $type,
                    $input['attributes'],
                    $input['relationships']
                );

                // Process entity creation (validation + persist, flush handled by WriteListener)
                $entity = $this->processor->processCreate($type, $changes, $input['id']);

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

        /**
         * @var array{
         *     data: array{
         *         id: string,
         *         links: array<string, string>,
         *         type: string
         *     }
         * } $document
         */
        $document = $this->document->buildResource($type, $model, new Criteria(), $request);
        $resourceId = $document['data']['id'];

        // Dispatch event after successful creation
        $this->eventDispatcher->dispatch(
            new ResourceChangedEvent($type, $resourceId, 'create')
        );

        $self = $document['data']['links']['self'] ?? $this->links->resourceSelf($type, $resourceId);

        return $this->responseFactory->created($document, $self);
    }
}
