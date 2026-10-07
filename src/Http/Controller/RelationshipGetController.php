<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Controller;

use AlexFigures\Symfony\Http\Authorization\RelationshipAccessChecker;
use AlexFigures\Symfony\Http\Authorization\RelationshipOperation;
use AlexFigures\Symfony\Http\Error\ErrorMapper;
use AlexFigures\Symfony\Http\Exception\MethodNotAllowedException;
use AlexFigures\Symfony\Http\Negotiation\MediaType;
use AlexFigures\Symfony\Http\Relationship\LinkageBuilder;
use AlexFigures\Symfony\Resource\Definition\ResourceOperation;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/api/{type}/{id}/relationships/{rel}', methods: ['GET', 'HEAD'], name: 'jsonapi.relationship.get')]
final class RelationshipGetController
{
    public function __construct(
        private readonly LinkageBuilder $linkage,
        private readonly ResourceRegistryInterface $registry,
        private readonly ErrorMapper $errors,
        private readonly ?\AlexFigures\Symfony\Http\Link\LinkGenerator $links = null,
        private readonly ?RelationshipAccessChecker $access = null,
    ) {
    }

    public function __invoke(Request $request, string $type, string $id, string $rel): JsonResponse
    {
        $metadata = $this->registry->getByType($type);
        $this->assertOperationAllowed(ResourceOperation::SHOW, $metadata->allowedOperations);

        $this->access?->assertGranted($request, $type, $id, $rel, RelationshipOperation::READ_LINKAGE);

        return $this->currentRepresentation($request, $type, $id, $rel);
    }

    /** @internal Used to evaluate validators before relationship mutation. */
    public function currentRepresentation(Request $request, string $type, string $id, string $rel): JsonResponse
    {
        [, $data] = $this->linkage->read($type, $id, $rel, $request);

        $document = [
            'jsonapi' => ['version' => '1.1'],
            'links' => ['self' => $request->getUri()],
            'data' => $data,
        ];

        if ($this->links !== null) {
            $document['links']['related'] = $this->links->relationshipRelated($type, $id, $rel);
        }

        $response = new \AlexFigures\Symfony\Http\Controller\Support\RepresentationResponse(
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
