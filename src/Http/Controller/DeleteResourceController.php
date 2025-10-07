<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Controller;

use JsonApi\Symfony\Contract\Data\ResourcePersister;
use JsonApi\Symfony\Contract\Tx\TransactionManager;
use JsonApi\Symfony\Events\ResourceChangedEvent;
use JsonApi\Symfony\Http\Exception\NotFoundException;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(path: '/api/{type}/{id}', methods: ['DELETE'], name: 'jsonapi.delete')]
final class DeleteResourceController
{
    public function __construct(
        private readonly ResourceRegistryInterface $registry,
        private readonly ResourcePersister $persister,
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
            $this->persister->delete($type, $id);
        });

        // Dispatch event after successful deletion
        $this->eventDispatcher->dispatch(
            new ResourceChangedEvent($type, $id, 'delete')
        );

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
