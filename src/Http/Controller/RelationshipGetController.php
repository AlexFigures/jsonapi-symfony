<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Controller;

use JsonApi\Symfony\Http\Negotiation\MediaType;
use JsonApi\Symfony\Http\Relationship\LinkageBuilder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/api/{type}/{id}/relationships/{rel}', methods: ['GET'], name: 'jsonapi.relationship.get')]
final class RelationshipGetController
{
    public function __construct(private readonly LinkageBuilder $linkage)
    {
    }

    public function __invoke(Request $request, string $type, string $id, string $rel): JsonResponse
    {
        [, $data] = $this->linkage->read($type, $id, $rel, $request);

        return new JsonResponse(
            [
                'jsonapi' => ['version' => '1.1'],
                'links' => ['self' => $request->getUri()],
                'data' => $data,
            ],
            JsonResponse::HTTP_OK,
            ['Content-Type' => MediaType::JSON_API],
        );
    }
}
