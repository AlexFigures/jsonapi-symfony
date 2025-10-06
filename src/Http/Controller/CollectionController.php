<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Controller;

use JsonApi\Symfony\Contract\Data\ResourceRepository;
use JsonApi\Symfony\Http\Document\DocumentBuilder;
use JsonApi\Symfony\Http\Request\QueryParser;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/api/{type}', name: 'jsonapi.collection', methods: ['GET'])]
final class CollectionController
{
    public function __construct(
        private readonly ResourceRegistryInterface $registry,
        private readonly ResourceRepository $repository,
        private readonly QueryParser $parser,
        private readonly DocumentBuilder $document,
    ) {
    }

    public function __invoke(Request $request, string $type): JsonResponse
    {
        if (!$this->registry->hasType($type)) {
            throw new NotFoundHttpException(sprintf('Resource type "%s" not found.', $type));
        }

        $criteria = $this->parser->parse($type, $request);
        $slice = $this->repository->findCollection($type, $criteria);
        $document = $this->document->buildCollection($type, $slice->items, $criteria, $slice, $request);

        return $this->createResponse($document);
    }

    /**
     * @param array<string, mixed> $document
     */
    private function createResponse(array $document): JsonResponse
    {
        $response = new JsonResponse($document, Response::HTTP_OK);
        $response->headers->set('Content-Type', 'application/vnd.api+json');

        return $response;
    }
}
