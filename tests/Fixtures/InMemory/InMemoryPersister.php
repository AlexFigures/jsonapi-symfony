<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Fixtures\InMemory;

use JsonApi\Symfony\Contract\Data\ChangeSet;
use JsonApi\Symfony\Contract\Data\ResourcePersister;
use JsonApi\Symfony\Http\Exception\ConflictException;
use JsonApi\Symfony\Http\Exception\NotFoundException;
use JsonApi\Symfony\Resource\Metadata\ResourceMetadata;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Uid\Uuid;

final class InMemoryPersister implements ResourcePersister
{
    private PropertyAccessorInterface $accessor;

    public function __construct(
        private readonly InMemoryRepository $repository,
        private readonly ResourceRegistryInterface $registry,
        private readonly ?InMemoryTransactionManager $transactionManager = null,
        ?PropertyAccessorInterface $accessor = null,
    ) {
        $this->accessor = $accessor ?? PropertyAccess::createPropertyAccessor();
    }

    public function create(string $type, ChangeSet $changes, ?string $clientId = null): object
    {
        $metadata = $this->metadata($type);
        $id = $clientId ?? Uuid::v4()->toRfc4122();

        if ($this->repository->has($type, $id)) {
            throw new ConflictException(sprintf('Resource "%s" with id "%s" already exists.', $type, $id));
        }

        $model = $this->repository->createPrototype($type);
        $this->assignId($metadata, $model, $id);
        $this->applyAttributes($model, $changes);

        $this->repository->save($type, $model);

        // Register rollback callback
        $this->transactionManager?->onRollback(function () use ($type, $id): void {
            $this->repository->remove($type, $id);
        });

        return $model;
    }

    public function update(string $type, string $id, ChangeSet $changes): object
    {
        $model = $this->repository->get($type, $id);
        if ($model === null) {
            throw new NotFoundException(sprintf('Resource "%s" with id "%s" was not found.', $type, $id));
        }

        // Store original state for rollback
        $originalModel = clone $model;

        $this->applyAttributes($model, $changes);
        $this->repository->save($type, $model);

        // Register rollback callback
        $this->transactionManager?->onRollback(function () use ($type, $originalModel): void {
            $this->repository->save($type, $originalModel);
        });

        return $model;
    }

    public function delete(string $type, string $id): void
    {
        if (!$this->repository->has($type, $id)) {
            throw new NotFoundException(sprintf('Resource "%s" with id "%s" was not found.', $type, $id));
        }

        // Store model for rollback
        $deletedModel = $this->repository->get($type, $id);

        $this->repository->remove($type, $id);

        // Register rollback callback
        if ($deletedModel !== null) {
            $this->transactionManager?->onRollback(function () use ($type, $deletedModel): void {
                $this->repository->save($type, $deletedModel);
            });
        }
    }

    private function applyAttributes(object $model, ChangeSet $changes): void
    {
        foreach ($changes->attributes as $path => $value) {
            $this->accessor->setValue($model, $path, $value);
        }
    }

    private function assignId(ResourceMetadata $metadata, object $model, string $id): void
    {
        $path = $metadata->idPropertyPath ?? 'id';
        $this->accessor->setValue($model, $path, $id);
    }

    private function metadata(string $type): ResourceMetadata
    {
        return $this->registry->getByType($type);
    }
}
