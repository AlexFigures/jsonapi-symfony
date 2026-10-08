<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Http\Controller;

use AlexFigures\JsonApi\Contract\Data\ResourceRepository;
use AlexFigures\JsonApi\Http\Controller\Support\JsonApiResponseFactory;
use AlexFigures\JsonApi\Http\Controller\Support\OperationValidator;
use AlexFigures\JsonApi\Http\Document\DocumentBuilder;
use AlexFigures\JsonApi\Http\Exception\NotFoundException;
use AlexFigures\JsonApi\Http\Request\QueryParser;
use AlexFigures\JsonApi\Resource\Definition\ResourceOperation;
use AlexFigures\JsonApi\Resource\Registry\ResourceRegistryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/api/{type}', name: 'jsonapi.collection', methods: ['GET', 'HEAD'])]
/** @internal */
final readonly class CollectionController
{
    public function __construct(
        private ResourceRegistryInterface $registry,
        private OperationValidator $operationValidator,
        private JsonApiResponseFactory $responseFactory,
        private ResourceRepository $repository,
        private QueryParser $parser,
        private DocumentBuilder $document,
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
