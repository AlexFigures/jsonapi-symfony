<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Http\Controller;

use AlexFigures\JsonApi\Contract\Data\RelationshipReader;
use AlexFigures\JsonApi\Http\Authorization\RelationshipAccessChecker;
use AlexFigures\JsonApi\Http\Authorization\RelationshipOperation;
use AlexFigures\JsonApi\Http\Document\DocumentBuilder;
use AlexFigures\JsonApi\Http\Error\ErrorMapper;
use AlexFigures\JsonApi\Http\Exception\MethodNotAllowedException;
use AlexFigures\JsonApi\Http\Exception\NotFoundException;
use AlexFigures\JsonApi\Http\Negotiation\MediaType;
use AlexFigures\JsonApi\Http\Request\QueryParser;
use AlexFigures\JsonApi\Resource\Definition\ResourceOperation;
use AlexFigures\JsonApi\Resource\Metadata\RelationshipMetadata;
use AlexFigures\JsonApi\Resource\Registry\ResourceRegistryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/api/{type}/{id}/{rel}', methods: ['GET', 'HEAD'], name: 'jsonapi.related')]
/** @internal */
final readonly class RelatedController
{
    public function __construct(
        private ResourceRegistryInterface $registry,
        private RelationshipReader $reader,
        private QueryParser $parser,
        private DocumentBuilder $document,
        private ErrorMapper $errors,
        private ?\AlexFigures\JsonApi\Contract\Data\ResourceRepository $repository = null,
        private ?RelationshipAccessChecker $access = null,
    ) {
    }

    public function __invoke(Request $request, string $type, string $id, string $rel): JsonResponse
    {
        $request->attributes->set('_jsonapi_related_endpoint', true);
        if (!$this->registry->hasType($type)) {
            throw new NotFoundException(sprintf('Resource type "%s" not found.', $type));
        }

        $metadata = $this->registry->getByType($type);
        $this->assertOperationAllowed(ResourceOperation::SHOW, $metadata->allowedOperations);
        $relationship = $metadata->relationships[$rel] ?? null;

        if (!$relationship instanceof RelationshipMetadata) {
            throw new NotFoundException(sprintf('Relationship "%s" not found on resource "%s".', $rel, $type));
        }

        $this->access?->assertGranted($request, $type, $id, $rel, RelationshipOperation::READ_RELATED);

        if ($relationship->toMany) {
            $request->attributes->set('_jsonapi_relationship_collection', true);
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
                if ($criteria->customConditions !== [] && $this->repository !== null) {
                    $targetMetadata = $this->registry->getByType($targetType);
                    $accessor = \Symfony\Component\PropertyAccess\PropertyAccess::createPropertyAccessor();
                    $idValue = $accessor->getValue($model, $targetMetadata->idPropertyPath ?? 'id');
                    if (is_scalar($idValue) || $idValue instanceof \Stringable) {
                        $model = $this->repository->findOne($targetType, (string) $idValue, $criteria);
                    }
                }
                $document = $model === null ? ['jsonapi' => ['version' => '1.1'], 'links' => ['self' => $request->getUri()], 'data' => null] : $this->document->buildResource($targetType, $model, $criteria, $request);
            }
        }

        $response = new \AlexFigures\JsonApi\Http\Controller\Support\RepresentationResponse(
            $document,
            JsonResponse::HTTP_OK,
            ['Content-Type' => MediaType::JSON_API],
        );

        // For HEAD requests, clear the content but keep all headers
        if ($request->isMethod('HEAD')) {
            $response->representationContent = (string) $response->getContent();
            $response->setContent('');
        }

        return $response;
    }

    /**
     * @param list<ResourceOperation> $allowedOperations
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
