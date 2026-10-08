<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Http\Controller;

use AlexFigures\JsonApi\Contract\Data\ResourceRepository;
use AlexFigures\JsonApi\Http\Controller\Support\JsonApiResponseFactory;
use AlexFigures\JsonApi\Http\Controller\Support\OperationValidator;
use AlexFigures\JsonApi\Http\Document\DocumentBuilder;
use AlexFigures\JsonApi\Http\Error\ErrorMapper;
use AlexFigures\JsonApi\Http\Exception\NotFoundException;
use AlexFigures\JsonApi\Http\Request\QueryParser;
use AlexFigures\JsonApi\Resource\Definition\ResourceOperation;
use AlexFigures\JsonApi\Resource\Registry\ResourceRegistryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/api/{type}/{id}', name: 'jsonapi.resource', methods: ['GET', 'HEAD'])]
/** @internal */
final readonly class ResourceController
{
    public function __construct(
        private ResourceRegistryInterface $registry,
        private OperationValidator $operationValidator,
        private JsonApiResponseFactory $responseFactory,
        private ResourceRepository $repository,
        private QueryParser $parser,
        private DocumentBuilder $document,
        private ErrorMapper $errors,
    ) {
    }

    public function __invoke(Request $request, string $type, string $id): JsonResponse
    {
        if (!$this->registry->hasType($type)) {
            $error = $this->errors->notFound(sprintf('Resource type "%s" not found.', $type));
            throw new NotFoundException(sprintf('Resource type "%s" not found.', $type), [$error]);
        }

        // Check if SHOW operation is allowed
        $metadata = $this->registry->getByType($type);
        $this->operationValidator->assertAllowed(ResourceOperation::SHOW, $metadata->allowedOperations);

        return $this->currentRepresentation($request, $type, $id);
    }

    /** @internal Used to evaluate write validators without imposing the SHOW policy. */
    public function currentRepresentation(Request $request, string $type, string $id): JsonResponse
    {
        $criteria = $this->parser->parse($type, $request);
        $model = $this->repository->findOne($type, $id, $criteria);

        if ($model === null) {
            $error = $this->errors->notFound(sprintf('Resource "%s" with id "%s" was not found.', $type, $id));
            throw new NotFoundException(sprintf('Resource "%s" with id "%s" was not found.', $type, $id), [$error]);
        }

        $document = $this->document->buildResource($type, $model, $criteria, $request);

        return $this->responseFactory->create($document, 200, $request->isMethod('HEAD'));
    }

}
