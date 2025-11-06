<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Controller;

use AlexFigures\Symfony\Contract\Data\RelationshipUpdater;
use AlexFigures\Symfony\Contract\Tx\TransactionManager;
use AlexFigures\Symfony\Events\RelationshipChangedEvent;
use AlexFigures\Symfony\Http\Controller\Support\OperationValidator;
use AlexFigures\Symfony\Http\Controller\Support\RequestDecoder;
use AlexFigures\Symfony\Http\Negotiation\MediaType;
use AlexFigures\Symfony\Http\Relationship\LinkageBuilder;
use AlexFigures\Symfony\Http\Relationship\WriteRelationshipsResponseConfig;
use AlexFigures\Symfony\Http\Write\RelationshipDocumentValidator;
use AlexFigures\Symfony\Resource\Definition\ResourceOperation;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(path: '/api/{type}/{id}/relationships/{rel}', methods: ['PATCH', 'POST', 'DELETE'], name: 'jsonapi.relationship.write')]
final class RelationshipWriteController
{
    public function __construct(
        private readonly OperationValidator $operationValidator,
        private readonly RequestDecoder $requestDecoder,
        private readonly RelationshipDocumentValidator $validator,
        private readonly RelationshipUpdater $updater,
        private readonly LinkageBuilder $linkage,
        private readonly WriteRelationshipsResponseConfig $responseConfig,
        private readonly TransactionManager $transaction,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ResourceRegistryInterface $registry,
    ) {
    }

    public function __invoke(Request $request, string $type, string $id, string $rel): Response
    {
        $metadata = $this->registry->getByType($type);
        $this->operationValidator->assertAllowed(ResourceOperation::UPDATE, $metadata->allowedOperations);

        $payload = $this->requestDecoder->decode($request);
        /** @var array{kind: 'to-one'|'to-many', data: null|array{type: string, id: string}|list<array{type: string, id: string}>} $validated */
        $validated = $this->validator->validate($type, $id, $rel, $payload, $request->getMethod());
        $kind = $validated['kind'];
        $data = $validated['data'];

        // Execute relationship update within a transaction
        $this->transaction->transactional(function () use ($request, $kind, $data, $type, $id, $rel): void {
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

}
