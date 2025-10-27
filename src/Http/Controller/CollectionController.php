<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Controller;

use AlexFigures\Symfony\Contract\Data\ResourceRepository;
use AlexFigures\Symfony\Http\Document\DocumentBuilder;
use AlexFigures\Symfony\Http\Error\ErrorMapper;
use AlexFigures\Symfony\Http\Exception\MethodNotAllowedException;
use AlexFigures\Symfony\Http\Exception\NotFoundException;
use AlexFigures\Symfony\Http\Request\QueryParser;
use AlexFigures\Symfony\Resource\Definition\ResourceOperation;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/api/{type}', name: 'jsonapi.collection', methods: ['GET', 'HEAD'])]
final class CollectionController
{
    public function __construct(
        private readonly ResourceRegistryInterface $registry,
        private readonly ResourceRepository $repository,
        private readonly QueryParser $parser,
        private readonly DocumentBuilder $document,
        private readonly ErrorMapper $errors,
    ) {
    }

    public function __invoke(Request $request, string $type): JsonResponse
    {
        if (!$this->registry->hasType($type)) {
            throw new NotFoundException(sprintf('Resource type "%s" not found.', $type));
        }

        // Check if INDEX operation is allowed
        $metadata = $this->registry->getByType($type);
        $this->assertOperationAllowed(ResourceOperation::INDEX, $metadata->allowedOperations);

        $criteria = $this->parser->parse($type, $request);
        $slice = $this->repository->findCollection($type, $criteria);
        $document = $this->document->buildCollection($type, $slice->items, $criteria, $slice, $request);

        return $this->createResponse($document, $request->isMethod('HEAD'));
    }

    /**
     * @param array<string, mixed> $document
     */
    private function createResponse(array $document, bool $isHead = false): JsonResponse
    {
        $response = new JsonResponse($document, Response::HTTP_OK);
        $response->headers->set('Content-Type', 'application/vnd.api+json');

        // For HEAD requests, clear the content but keep all headers
        if ($isHead) {
            $response->setContent('');
        }

        return $response;
    }

    /**
     * Assert that an operation is allowed for the resource.
     *
     * @param list<ResourceOperation> $allowedOperations
     *
     * @throws MethodNotAllowedException
     */
    private function assertOperationAllowed(ResourceOperation $operation, array $allowedOperations): void
    {
        foreach ($allowedOperations as $allowed) {
            if ($allowed === $operation) {
                return;
            }
        }

        // Collect all allowed HTTP methods from allowed operations
        $allowedMethods = [];
        foreach ($allowedOperations as $allowed) {
            $allowedMethods = array_merge($allowedMethods, $allowed->httpMethods());
        }
        $allowedMethods = array_values(array_unique($allowedMethods));

        $error = $this->errors->methodNotAllowed($allowedMethods);
        throw new MethodNotAllowedException($allowedMethods, 'Operation not allowed', [$error]);
    }
}
