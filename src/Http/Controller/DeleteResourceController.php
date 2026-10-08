<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Http\Controller;

use AlexFigures\JsonApi\Contract\Data\ResourceProcessor;
use AlexFigures\JsonApi\Contract\Tx\TransactionManager;
use AlexFigures\JsonApi\Events\ResourceChangedEvent;
use AlexFigures\JsonApi\Http\Controller\Support\OperationValidator;
use AlexFigures\JsonApi\Http\Exception\NotFoundException;
use AlexFigures\JsonApi\Http\Validation\DatabaseErrorMapper;
use AlexFigures\JsonApi\Resource\Definition\ResourceOperation;
use AlexFigures\JsonApi\Resource\Registry\ResourceRegistryInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(path: '/api/{type}/{id}', methods: ['DELETE'], name: 'jsonapi.delete')]
/** @internal */
final readonly class DeleteResourceController
{
    public function __construct(
        private ResourceRegistryInterface $registry,
        private OperationValidator $operationValidator,
        private ResourceProcessor $processor,
        private TransactionManager $transaction,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(string $type, string $id): Response
    {
        if (!$this->registry->hasType($type)) {
            throw new NotFoundException(sprintf('Resource type "%s" not found.', $type));
        }

        // Check if DELETE operation is allowed
        $metadata = $this->registry->getByType($type);
        $this->operationValidator->assertAllowed(ResourceOperation::DELETE, $metadata->allowedOperations);

        \AlexFigures\JsonApi\Tx\TransactionScope::write($this->transaction, $this->registry, $type, function () use ($type, $id): void {
            // Process entity deletion (remove + schedule flush, flush handled by WriteListener)
            $this->processor->processDelete($type, $id);
        });

        // Dispatch event after successful deletion
        $this->eventDispatcher->dispatch(
            new ResourceChangedEvent($type, $id, 'delete')
        );

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

}
