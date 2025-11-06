<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Controller;

use AlexFigures\Symfony\Contract\Data\ResourceRepository;
use AlexFigures\Symfony\Http\Controller\Support\JsonApiResponseFactory;
use AlexFigures\Symfony\Http\Controller\Support\OperationValidator;
use AlexFigures\Symfony\Http\Document\DocumentBuilder;
use AlexFigures\Symfony\Http\Exception\NotFoundException;
use AlexFigures\Symfony\Http\Request\QueryParser;
use AlexFigures\Symfony\Resource\Definition\ResourceOperation;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/api/{type}', name: 'jsonapi.collection', methods: ['GET', 'HEAD'])]
final class CollectionController
{
    public function __construct(
        private readonly ResourceRegistryInterface $registry,
        private readonly OperationValidator $operationValidator,
        private readonly JsonApiResponseFactory $responseFactory,
        private readonly ResourceRepository $repository,
        private readonly QueryParser $parser,
        private readonly DocumentBuilder $document,
    ) {
    }

    public function __invoke(Request $request, string $type): JsonResponse
    {
        if (!$this->registry->hasType($type)) {
            throw new NotFoundException(sprintf('Resource type "%s" not found.', $type));
        }

        // Check if INDEX operation is allowed
        $metadata = $this->registry->getByType($type);
        $this->operationValidator->assertAllowed(ResourceOperation::INDEX, $metadata->allowedOperations);

        $criteria = $this->parser->parse($type, $request);
        $slice = $this->repository->findCollection($type, $criteria);
        $document = $this->document->buildCollection($type, $slice->items, $criteria, $slice, $request);

        return $this->responseFactory->create($document, 200, $request->isMethod('HEAD'));
    }

}
