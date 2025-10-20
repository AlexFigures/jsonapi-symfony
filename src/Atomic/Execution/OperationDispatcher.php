<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Atomic\Execution;

use AlexFigures\Symfony\Atomic\Execution\Handlers\AddHandler;
use AlexFigures\Symfony\Atomic\Execution\Handlers\RelationshipOps;
use AlexFigures\Symfony\Atomic\Execution\Handlers\RemoveHandler;
use AlexFigures\Symfony\Atomic\Execution\Handlers\UpdateHandler;
use AlexFigures\Symfony\Atomic\Lid\LidRegistry;
use AlexFigures\Symfony\Atomic\Operation;
use AlexFigures\Symfony\Atomic\Result\ResultBuilder;
use AlexFigures\Symfony\Bridge\Doctrine\Flush\FlushManager;

final class OperationDispatcher
{
    public function __construct(
        private readonly AtomicTransaction $transaction,
        private readonly AddHandler $add,
        private readonly UpdateHandler $update,
        private readonly RemoveHandler $remove,
        private readonly RelationshipOps $relationships,
        private readonly ResultBuilder $results,
        private readonly FlushManager $flushManager,
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
                } else {
                    $outcomes[] = match ($operation->op) {
                        'add' => $this->add->handle($operation, $lids),
                        'update' => $this->update->handle($operation, $lids),
                        'remove' => $this->remove->handle($operation, $lids),
                        default => OperationOutcome::empty(),
                    };
                }

                // Flush after each operation to make entities available for subsequent operations
                // This is critical for LID resolution: entities created in operation N must be
                // available in the database for operation N+1 to reference them
                $this->flushManager->flush();
            }

            return $this->results->build($operations, $outcomes);
        });
    }
}
