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

#[Route(path: '/api/{type}/{id}', name: 'jsonapi.resource', methods: ['GET', 'HEAD'])]
final class ResourceController
{
    public function __construct(
        private readonly ResourceRegistryInterface $registry,
        private readonly ResourceRepository $repository,
        private readonly QueryParser $parser,
        private readonly DocumentBuilder $document,
    ) {
    }

    public function __invoke(Request $request, string $type, string $id): JsonResponse
    {
        if (!$this->registry->hasType($type)) {
            throw new NotFoundHttpException(sprintf('Resource type "%s" not found.', $type));
        }

        $criteria = $this->parser->parse($type, $request);
        $model = $this->repository->findOne($type, $id, $criteria);

        if ($model === null) {
            throw new NotFoundHttpException(sprintf('Resource "%s" with id "%s" was not found.', $type, $id));
        }

        $document = $this->document->buildResource($type, $model, $criteria, $request);

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
}
