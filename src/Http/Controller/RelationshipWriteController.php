<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Http\Controller;

use AlexFigures\JsonApi\Contract\Data\RelationshipUpdater;
use AlexFigures\JsonApi\Contract\Tx\TransactionManager;
use AlexFigures\JsonApi\Events\RelationshipChangedEvent;
use AlexFigures\JsonApi\Http\Authorization\RelationshipAccessChecker;
use AlexFigures\JsonApi\Http\Authorization\RelationshipOperation;
use AlexFigures\JsonApi\Http\Controller\Support\OperationValidator;
use AlexFigures\JsonApi\Http\Controller\Support\RequestDecoder;
use AlexFigures\JsonApi\Http\Negotiation\MediaType;
use AlexFigures\JsonApi\Http\Relationship\LinkageBuilder;
use AlexFigures\JsonApi\Http\Relationship\WriteRelationshipsResponseConfig;
use AlexFigures\JsonApi\Http\Write\RelationshipDocumentValidator;
use AlexFigures\JsonApi\Resource\Definition\ResourceOperation;
use AlexFigures\JsonApi\Resource\Registry\ResourceRegistryInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(path: '/api/{type}/{id}/relationships/{rel}', methods: ['PATCH', 'POST', 'DELETE'], name: 'jsonapi.relationship.write')]
/** @internal */
final readonly class RelationshipWriteController
{
    public function __construct(
        private OperationValidator $operationValidator,
        private RequestDecoder $requestDecoder,
        private RelationshipDocumentValidator $validator,
        private RelationshipUpdater $updater,
        private LinkageBuilder $linkage,
        private WriteRelationshipsResponseConfig $responseConfig,
        private TransactionManager $transaction,
        private EventDispatcherInterface $eventDispatcher,
        private ResourceRegistryInterface $registry,
        private ?RelationshipAccessChecker $access = null,
    ) {
    }

    public function __invoke(Request $request, string $type, string $id, string $rel): Response
    {
        $metadata = $this->registry->getByType($type);
        $this->operationValidator->assertAllowed(ResourceOperation::UPDATE, $metadata->allowedOperations);

        $this->assertAccess($request, $type, $id, $rel);

        $payload = $this->requestDecoder->decode($request);
        /** @var array{kind: 'to-one'|'to-many', data: null|array{type: string, id: string}|list<array{type: string, id: string}>} $validated */
        $validated = $this->validator->validate($type, $id, $rel, $payload, $request->getMethod());
        $kind = $validated['kind'];
        $data = $validated['data'];

        // Execute relationship update within a transaction
        \AlexFigures\JsonApi\Tx\TransactionScope::run($this->transaction, $this->registry, [$type], function () use ($request, $kind, $data, $type, $id, $rel): void {
            if ($request->isMethod('PATCH')) {
                if ($kind === 'to-one') {
                    /** @var array{type: string, id: string}|null $data */
                    $this->updater->replaceToOne($type, $id, $rel, $this->linkage->toIdentifierOrNull($data));
                } else {
                    /** @var list<array{type: string, id: string}> $data */
                    $this->updater->replaceToMany($type, $id, $rel, $this->linkage->toIdentifiers($data));
                }
            } elseif ($request->isMethod('POST')) {
                /** @var list<array{type: string, id: string}> $data */
                $this->updater->addToMany($type, $id, $rel, $this->linkage->toIdentifiers($data));
            } else {
                /** @var list<array{type: string, id: string}> $data */
                $this->updater->removeFromToMany($type, $id, $rel, $this->linkage->toIdentifiers($data));
            }
        });

        // Dispatch event after successful transaction
        $operation = match (true) {
            $request->isMethod('PATCH') => 'replace',
            $request->isMethod('POST') => 'add',
            $request->isMethod('DELETE') => 'remove',
            default => throw new RuntimeException('Unsupported HTTP method'),
        };

        $this->eventDispatcher->dispatch(
            new RelationshipChangedEvent($type, $id, $rel, $operation)
        );

        if ($this->responseConfig->mode === '204') {
            return new Response(null, Response::HTTP_NO_CONTENT, ['Content-Type' => MediaType::JSON_API]);
        }

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

    /** @internal Also used before write precondition reads. */
    public function assertAccess(Request $request, string $type, string $id, string $rel): void
    {
        $operation = match ($request->getMethod()) {
            'PATCH' => RelationshipOperation::REPLACE,
            'POST' => RelationshipOperation::ADD,
            'DELETE' => RelationshipOperation::REMOVE,
            default => throw new RuntimeException('Unsupported HTTP method'),
        };
        $this->access?->assertGranted($request, $type, $id, $rel, $operation);
    }

}
