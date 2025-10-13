<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Controller;

use AlexFigures\Symfony\Contract\Data\ResourceProcessor;
use AlexFigures\Symfony\Contract\Tx\TransactionManager;
use AlexFigures\Symfony\Events\ResourceChangedEvent;
use AlexFigures\Symfony\Http\Exception\NotFoundException;
use AlexFigures\Symfony\Http\Validation\DatabaseErrorMapper;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(path: '/api/{type}/{id}', methods: ['DELETE'], name: 'jsonapi.delete')]
final class DeleteResourceController
{
    public function __construct(
        private readonly ResourceRegistryInterface $registry,
        private readonly ResourceProcessor $processor,
        private readonly TransactionManager $transaction,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(string $type, string $id): Response
    {
        if (!$this->registry->hasType($type)) {
            throw new NotFoundException(sprintf('Resource type "%s" not found.', $type));
        }

        $this->transaction->transactional(function () use ($type, $id): void {
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
