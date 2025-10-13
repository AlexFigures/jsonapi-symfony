<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Controller;

use AlexFigures\Symfony\Contract\Data\RelationshipReader;
use AlexFigures\Symfony\Http\Document\DocumentBuilder;
use AlexFigures\Symfony\Http\Exception\NotFoundException;
use AlexFigures\Symfony\Http\Negotiation\MediaType;
use AlexFigures\Symfony\Http\Request\QueryParser;
use AlexFigures\Symfony\Resource\Metadata\RelationshipMetadata;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/api/{type}/{id}/{rel}', methods: ['GET', 'HEAD'], name: 'jsonapi.related')]
final class RelatedController
{
    public function __construct(
        private readonly ResourceRegistryInterface $registry,
        private readonly RelationshipReader $reader,
        private readonly QueryParser $parser,
        private readonly DocumentBuilder $document,
    ) {
    }

    public function __invoke(Request $request, string $type, string $id, string $rel): JsonResponse
    {
        if (!$this->registry->hasType($type)) {
            throw new NotFoundException(sprintf('Resource type "%s" not found.', $type));
        }

        $metadata = $this->registry->getByType($type);
        $relationship = $metadata->relationships[$rel] ?? null;

        if (!$relationship instanceof RelationshipMetadata) {
            throw new NotFoundException(sprintf('Relationship "%s" not found on resource "%s".', $rel, $type));
        }

        if ($relationship->toMany) {
            $targetType = $relationship->targetType ?? $rel;
            $criteria = $this->parser->parse($targetType, $request);
            $slice = $this->reader->getRelatedCollection($type, $id, $rel, $criteria);
            $document = $this->document->buildCollection($targetType, $slice->items, $criteria, $slice, $request);
        } else {
            $model = $this->reader->getRelatedResource($type, $id, $rel);

            if ($model === null) {
                $document = [
                    'jsonapi' => ['version' => '1.1'],
                    'links' => ['self' => $request->getUri()],
                    'data' => null,
                ];
            } else {
                $targetType = $relationship->targetType;
                if ($targetType === null) {
                    $targetMetadata = $this->registry->getByClass($model::class);
                    if ($targetMetadata === null) {
                        throw new NotFoundException(sprintf('Unable to resolve target type for relationship "%s".', $rel));
                    }

                    $targetType = $targetMetadata->type;
                }

                $criteria = $this->parser->parse($targetType, $request);
                $document = $this->document->buildResource($targetType, $model, $criteria, $request);
            }
        }

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
