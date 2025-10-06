<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Atomic\Execution;

use JsonApi\Symfony\Atomic\Execution\Handlers\AddHandler;
use JsonApi\Symfony\Atomic\Execution\Handlers\RelationshipOps;
use JsonApi\Symfony\Atomic\Execution\Handlers\RemoveHandler;
use JsonApi\Symfony\Atomic\Execution\Handlers\UpdateHandler;
use JsonApi\Symfony\Atomic\Lid\LidRegistry;
use JsonApi\Symfony\Atomic\Operation;
use JsonApi\Symfony\Atomic\Result\ResultBuilder;

final class OperationDispatcher
{
    public function __construct(
        private readonly AtomicTransaction $transaction,
        private readonly AddHandler $add,
        private readonly UpdateHandler $update,
        private readonly RemoveHandler $remove,
        private readonly RelationshipOps $relationships,
        private readonly ResultBuilder $results,
    ) {
    }

    /**
     * @param list<Operation> $operations
     *
     * @return array{0: list<array<string, mixed>>, 1: bool}
     */
    public function run(array $operations, LidRegistry $lids): array
    {
        return $this->transaction->run(function () use ($operations, $lids) {
            $outcomes = [];

            foreach ($operations as $operation) {
                if ($operation->isRelationshipOperation()) {
                    $outcomes[] = $this->relationships->handle($operation, $lids);
                    continue;
                }

                $outcomes[] = match ($operation->op) {
                    'add' => $this->add->handle($operation, $lids),
                    'update' => $this->update->handle($operation, $lids),
                    'remove' => $this->remove->handle($operation, $lids),
                    default => OperationOutcome::empty(),
                };
            }

            return $this->results->build($operations, $outcomes);
        });
    }
}
