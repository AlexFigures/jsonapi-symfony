<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Atomic\Execution\Handlers;

use JsonApi\Symfony\Atomic\Execution\OperationOutcome;
use JsonApi\Symfony\Atomic\Lid\LidRegistry;
use JsonApi\Symfony\Atomic\Operation;
use JsonApi\Symfony\Http\Exception\BadRequestException;
use JsonApi\Symfony\Http\Write\ChangeSetFactory;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;
use JsonApi\Symfony\Contract\Data\ResourceProcessor;
use Stringable;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

final class AddHandler
{
    public function __construct(
        private readonly ResourceProcessor $processor,
        private readonly ChangeSetFactory $changeSet,
        private readonly ResourceRegistryInterface $registry,
        private readonly PropertyAccessorInterface $accessor,
    ) {
    }

    public function handle(Operation $operation, LidRegistry $lids): OperationOutcome
    {
        $data = $operation->data;
        if (!is_array($data)) {
            throw new BadRequestException('The "data" member MUST be present for add operations.');
        }

        $type = $operation->ref?->type;
        if ($type === null && isset($data['type']) && is_string($data['type']) && $data['type'] !== '') {
            $type = $data['type'];
        }

        if ($type === null) {
            throw new BadRequestException('Unable to resolve resource type for add operation.');
        }

        $attributes = $data['attributes'] ?? null;
        if ($attributes === null) {
            $attributes = [];
        }

        if (!is_array($attributes)) {
            throw new BadRequestException('Resource attributes must be an object.');
        }

        /** @var array<string, mixed> $attributes */
        $changes = $this->changeSet->fromAttributes($type, $attributes);

        $clientId = null;
        if (isset($data['id']) && is_string($data['id']) && $data['id'] !== '') {
            $clientId = $data['id'];
        }

        $model = $this->processor->processCreate($type, $changes, $clientId);

        $metadata = $this->registry->getByType($type);
        $idProperty = $metadata->idPropertyPath ?? 'id';
        $idValue = $this->accessor->getValue($model, $idProperty);

        if (!is_scalar($idValue) && !($idValue instanceof Stringable)) {
            throw new BadRequestException('Unable to resolve resource identifier for persisted model.');
        }

        $id = (string) $idValue;

        if (isset($data['lid']) && is_string($data['lid']) && $data['lid'] !== '') {
            $lids->associate($data['lid'], $type, $id);
        }

        return OperationOutcome::forResource($type, $id, $model);
    }
}
