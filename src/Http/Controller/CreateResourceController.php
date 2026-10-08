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
use AlexFigures\JsonApi\Http\Exception\ForbiddenException;
use AlexFigures\JsonApi\Http\Exception\NotFoundException;
use AlexFigures\JsonApi\Http\Exception\UnprocessableEntityException;
use AlexFigures\JsonApi\Http\Exception\ValidationException;
use AlexFigures\JsonApi\Http\Link\LinkGenerator;
use AlexFigures\JsonApi\Http\Validation\ConstraintViolationMapper;
use AlexFigures\JsonApi\Http\Write\ChangeSetFactory;
use AlexFigures\JsonApi\Http\Write\InputDocumentValidator;
use AlexFigures\JsonApi\Http\Write\WriteConfig;
use AlexFigures\JsonApi\Query\Criteria;
use AlexFigures\JsonApi\Resource\Definition\ResourceOperation;
use AlexFigures\JsonApi\Resource\Registry\ResourceRegistryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(path: '/api/{type}', methods: ['POST'], name: 'jsonapi.create')]
/** @internal */
final readonly class CreateResourceController
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
        private LinkGenerator $links,
        private WriteConfig $writeConfig,
        private ConstraintViolationMapper $violationMapper,
        private EventDispatcherInterface $eventDispatcher,
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
            $model = \AlexFigures\JsonApi\Tx\TransactionScope::write($this->transaction, $this->registry, $type, function () use ($type, $input) {
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

        $request->attributes->set('_jsonapi_representation_flushed', true);
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
