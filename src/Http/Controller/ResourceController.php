<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Controller;

use AlexFigures\Symfony\Contract\Data\ResourceRepository;
use AlexFigures\Symfony\Http\Controller\Support\JsonApiResponseFactory;
use AlexFigures\Symfony\Http\Controller\Support\OperationValidator;
use AlexFigures\Symfony\Http\Document\DocumentBuilder;
use AlexFigures\Symfony\Http\Error\ErrorMapper;
use AlexFigures\Symfony\Http\Exception\NotFoundException;
use AlexFigures\Symfony\Http\Request\QueryParser;
use AlexFigures\Symfony\Resource\Definition\ResourceOperation;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/api/{type}/{id}', name: 'jsonapi.resource', methods: ['GET', 'HEAD'])]
final class ResourceController
{
    public function __construct(
        private readonly ResourceRegistryInterface $registry,
        private readonly OperationValidator $operationValidator,
        private readonly JsonApiResponseFactory $responseFactory,
        private readonly ResourceRepository $repository,
        private readonly QueryParser $parser,
        private readonly DocumentBuilder $document,
        private readonly ErrorMapper $errors,
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
