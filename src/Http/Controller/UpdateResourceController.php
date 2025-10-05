<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Controller;

use JsonApi\Symfony\Contract\Data\ResourcePersister;
use JsonApi\Symfony\Contract\Tx\TransactionManager;
use JsonApi\Symfony\Http\Document\DocumentBuilder;
use JsonApi\Symfony\Http\Exception\BadRequestException;
use JsonApi\Symfony\Http\Exception\NotFoundException;
use JsonApi\Symfony\Http\Exception\JsonApiHttpException;
use JsonApi\Symfony\Http\Negotiation\MediaType;
use JsonApi\Symfony\Http\Write\ChangeSetFactory;
use JsonApi\Symfony\Http\Write\InputDocumentValidator;
use JsonApi\Symfony\Query\Criteria;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/api/{type}/{id}', methods: ['PATCH'], name: 'jsonapi.update')]
final class UpdateResourceController
{
    public function __construct(
        private readonly ResourceRegistryInterface $registry,
        private readonly InputDocumentValidator $validator,
        private readonly ChangeSetFactory $changes,
        private readonly ResourcePersister $persister,
        private readonly TransactionManager $transaction,
        private readonly DocumentBuilder $document,
    ) {
    }

    public function __invoke(Request $request, string $type, string $id): Response
    {
        if (!$this->registry->hasType($type)) {
            throw new NotFoundException(sprintf('Resource type "%s" not found.', $type));
        }

        $payload = $this->decode($request);
        $input = $this->validator->validateAndExtract($type, $id, $payload, 'PATCH');

        $model = $this->transaction->transactional(function () use ($type, $id, $input) {
            $changes = $this->changes->fromAttributes($type, $input['attributes']);

            return $this->persister->update($type, $id, $changes);
        });

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
            throw JsonApiHttpException::unsupportedMediaType('JSON:API requires the "application/vnd.api+json" media type.');
        }

        $content = $request->getContent();

        if ($content === false || $content === '') {
            throw new BadRequestException('Request body must not be empty.');
        }

        $decoded = json_decode($content, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new BadRequestException(sprintf('Malformed JSON: %s.', json_last_error_msg()));
        }

        if (!is_array($decoded)) {
            throw new BadRequestException('Request body must be a valid JSON object.');
        }

        if (array_is_list($decoded)) {
            throw new BadRequestException('Request body must be a valid JSON object.');
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
