<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Atomic\Execution\Handlers;

use JsonApi\Symfony\Atomic\Execution\OperationOutcome;
use JsonApi\Symfony\Atomic\Lid\LidRegistry;
use JsonApi\Symfony\Atomic\Operation;
use JsonApi\Symfony\Contract\Data\RelationshipUpdater;
use JsonApi\Symfony\Contract\Data\ResourceIdentifier;
use JsonApi\Symfony\Http\Error\ErrorMapper;
use JsonApi\Symfony\Http\Exception\BadRequestException;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;

final class RelationshipOps
{
    public function __construct(
        private readonly RelationshipUpdater $relationships,
        private readonly ResourceRegistryInterface $registry,
        private readonly ErrorMapper $errors,
    ) {
    }

    public function handle(Operation $operation, LidRegistry $lids): OperationOutcome
    {
        $ref = $operation->ref;
        if ($ref === null || $ref->relationship === null) {
            throw new BadRequestException('Relationship operations require a relationship ref.');
        }

        $metadata = $this->registry->getByType($ref->type);
        $relationship = $metadata->relationships[$ref->relationship] ?? null;
        if ($relationship === null) {
            throw new BadRequestException('Unknown relationship.', [
                $this->errors->invalidPointer($operation->pointer . '/ref/relationship', sprintf('Relationship "%s" is not defined for resource "%s".', $ref->relationship, $ref->type)),
            ]);
        }

        $id = $this->resolveId($operation, $lids);

        if ($relationship->toMany) {
            $data = $operation->data;
            if (!is_array($data) || !array_is_list($data)) {
                throw new BadRequestException('Relationship data must be an array of resource identifiers.', [
                    $this->errors->invalidPointer($operation->pointer . '/data', 'To-many relationship data MUST be an array of resource identifiers.'),
                ]);
            }

            $identifiers = [];
            foreach ($data as $index => $identifier) {
                $identifiers[] = $this->identifierFromArray($identifier, sprintf('%s/data/%d', $operation->pointer, $index), $relationship->targetType, $lids);
            }

            if ($operation->op === 'add') {
                $this->relationships->addToMany($ref->type, $id, $ref->relationship, $identifiers);
            } elseif ($operation->op === 'remove') {
                $this->relationships->removeFromToMany($ref->type, $id, $ref->relationship, $identifiers);
            } else {
                $this->relationships->replaceToMany($ref->type, $id, $ref->relationship, $identifiers);
            }

            return OperationOutcome::empty();
        }

        if ($operation->op !== 'update') {
            throw new BadRequestException('Only update operations are allowed for to-one relationships.', [
                $this->errors->invalidPointer($operation->pointer . '/op', 'Only the "update" operation is allowed for to-one relationships.'),
            ]);
        }

        if ($operation->data === null) {
            $this->relationships->replaceToOne($ref->type, $id, $ref->relationship, null);

            return OperationOutcome::empty();
        }

        $identifier = $this->identifierFromArray($operation->data, $operation->pointer . '/data', $relationship->targetType, $lids);
        $this->relationships->replaceToOne($ref->type, $id, $ref->relationship, $identifier);

        return OperationOutcome::empty();
    }

    private function resolveId(Operation $operation, LidRegistry $lids): string
    {
        $ref = $operation->ref;
        if ($ref === null) {
            throw new BadRequestException('Relationship operations require a ref.');
        }

        if ($ref->id !== null) {
            return $ref->id;
        }

        if ($ref->lid !== null) {
            $id = $lids->resolveId($ref->lid);
            if ($id === null) {
                throw new BadRequestException('Unknown local identifier.', [
                    $this->errors->invalidPointer($operation->pointer . '/ref/lid', sprintf('Local identifier "%s" is not registered.', $ref->lid)),
                ]);
            }

            return $id;
        }

        throw new BadRequestException('Relationship operations require a resource identifier.', [
            $this->errors->invalidPointer($operation->pointer . '/ref', 'Relationship operations MUST specify a resource identifier.'),
        ]);
    }

    private function identifierFromArray(mixed $identifier, string $pointer, string $expectedType, LidRegistry $lids): ResourceIdentifier
    {
        if (!is_array($identifier) || array_is_list($identifier)) {
            throw new BadRequestException('Invalid resource identifier.', [
                $this->errors->invalidPointer($pointer, 'Resource identifiers MUST be objects.'),
            ]);
        }

        $type = $identifier['type'] ?? null;
        if (!is_string($type) || $type === '') {
            throw new BadRequestException('Invalid resource identifier.', [
                $this->errors->invalidPointer($pointer . '/type', 'Resource identifiers MUST contain a non-empty type.'),
            ]);
        }

        if ($type !== $expectedType) {
            throw new BadRequestException('Type mismatch.', [
                $this->errors->invalidPointer($pointer . '/type', sprintf('Resource type must be "%s", got "%s".', $expectedType, $type)),
            ]);
        }

        if (isset($identifier['id']) && is_string($identifier['id']) && $identifier['id'] !== '') {
            return new ResourceIdentifier($type, $identifier['id']);
        }

        if (isset($identifier['lid']) && is_string($identifier['lid']) && $identifier['lid'] !== '') {
            $id = $lids->resolveId($identifier['lid']);
            if ($id === null) {
                throw new BadRequestException('Unknown local identifier.', [
                    $this->errors->invalidPointer($pointer . '/lid', sprintf('Local identifier "%s" is not registered.', $identifier['lid'])),
                ]);
            }

            return new ResourceIdentifier($type, $id);
        }

        throw new BadRequestException('Resource identifiers must include an id or lid.', [
            $this->errors->invalidPointer($pointer, 'Resource identifiers MUST include an "id" or "lid" member.'),
        ]);
    }
}
