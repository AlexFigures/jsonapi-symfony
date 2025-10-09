<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Controller;

use JsonApi\Symfony\Contract\Data\ResourceProcessor;
use JsonApi\Symfony\Contract\Tx\TransactionManager;
use JsonApi\Symfony\Events\ResourceChangedEvent;
use JsonApi\Symfony\Http\Document\DocumentBuilder;
use JsonApi\Symfony\Http\Error\ErrorMapper;
use JsonApi\Symfony\Http\Exception\BadRequestException;
use JsonApi\Symfony\Http\Exception\NotFoundException;
use JsonApi\Symfony\Http\Exception\UnprocessableEntityException;
use JsonApi\Symfony\Http\Exception\UnsupportedMediaTypeException;
use JsonApi\Symfony\Http\Negotiation\MediaType;
use JsonApi\Symfony\Http\Validation\ConstraintViolationMapper;
use JsonApi\Symfony\Http\Validation\DatabaseErrorMapper;
use JsonApi\Symfony\Http\Write\ChangeSetFactory;
use JsonApi\Symfony\Http\Write\InputDocumentValidator;
use JsonApi\Symfony\Query\Criteria;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        private readonly InputDocumentValidator $validator,
        private readonly ChangeSetFactory $changes,
        private readonly ResourceProcessor $processor,
        private readonly TransactionManager $transaction,
        private readonly DocumentBuilder $document,
        private readonly ErrorMapper $errors,
        private readonly ConstraintViolationMapper $violationMapper,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(Request $request, string $type, string $id): Response
    {
        if (!$this->registry->hasType($type)) {
            throw new NotFoundException(sprintf('Resource type "%s" not found.', $type));
        }

        $payload = $this->decode($request);
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
        } catch (ValidationFailedException $exception) {
            $errors = $this->violationMapper->map($type, $exception->getViolations());

            throw new UnprocessableEntityException('Validation failed.', $errors, previous: $exception);
        }

        // Dispatch event after successful update
        $this->eventDispatcher->dispatch(
            new ResourceChangedEvent($type, $id, 'update')
        );

        $document = $this->document->buildResource($type, $model, new Criteria(), $request);

        return new JsonResponse($document, Response::HTTP_OK, ['Content-Type' => MediaType::JSON_API]);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Request $request): array
    {
        $contentType = $request->headers->get('Content-Type');
        if ($contentType !== null && MediaType::JSON_API !== $this->normalizeMediaType($contentType)) {
            throw new UnsupportedMediaTypeException($contentType, 'JSON:API requires the "application/vnd.api+json" media type.');
        }

        $content = (string) $request->getContent();

        if ($content === '') {
            throw new BadRequestException('Request body must not be empty.', [$this->errors->invalidPointer('/', 'Request body must not be empty.')]);
        }

        $decoded = json_decode($content, true);
        if ($decoded === null && json_last_error() !== \JSON_ERROR_NONE) {
            $error = $this->errors->invalidJson(new RuntimeException(sprintf('Malformed JSON: %s.', json_last_error_msg())));
            throw new BadRequestException('Malformed JSON.', [$error]);
        }

        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new BadRequestException('Request body must be a valid JSON object.', [$this->errors->invalidPointer('/', 'Request body must be a valid JSON object.')]);
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private function normalizeMediaType(string $value): string
    {
        $normalized = trim(strtolower($value));
        $semicolonPosition = strpos($normalized, ';');

        if ($semicolonPosition === false) {
            return $normalized;
        }

        return substr($normalized, 0, $semicolonPosition);
    }
}
