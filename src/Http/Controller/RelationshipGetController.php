<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Controller;

use JsonApi\Symfony\Http\Negotiation\MediaType;
use JsonApi\Symfony\Http\Relationship\LinkageBuilder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/api/{type}/{id}/relationships/{rel}', methods: ['GET', 'HEAD'], name: 'jsonapi.relationship.get')]
final class RelationshipGetController
{
    public function __construct(private readonly LinkageBuilder $linkage)
    {
    }

    public function __invoke(Request $request, string $type, string $id, string $rel): JsonResponse
    {
        [, $data] = $this->linkage->read($type, $id, $rel, $request);

        $document = [
            'jsonapi' => ['version' => '1.1'],
            'links' => ['self' => $request->getUri()],
            'data' => $data,
        ];

        $response = new JsonResponse(
            $document,
            JsonResponse::HTTP_OK,
            ['Content-Type' => MediaType::JSON_API],
        );

        // For HEAD requests, clear the content but keep all headers
        if ($request->isMethod('HEAD')) {
            $response->setContent('');
        }

        return $response;
    }
}
