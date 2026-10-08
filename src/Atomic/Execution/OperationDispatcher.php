<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Atomic\Execution;

use AlexFigures\JsonApi\Atomic\Execution\Handlers\AddHandler;
use AlexFigures\JsonApi\Atomic\Execution\Handlers\RelationshipOps;
use AlexFigures\JsonApi\Atomic\Execution\Handlers\RemoveHandler;
use AlexFigures\JsonApi\Atomic\Execution\Handlers\UpdateHandler;
use AlexFigures\JsonApi\Atomic\Lid\LidRegistry;
use AlexFigures\JsonApi\Atomic\Operation;
use AlexFigures\JsonApi\Atomic\Result\ResultBuilder;
use AlexFigures\JsonApi\Bridge\Doctrine\Flush\FlushManager;

/** @internal */
final readonly class OperationDispatcher
{
    public function __construct(
        private AtomicTransaction $transaction,
        private AddHandler $add,
        private UpdateHandler $update,
        private RemoveHandler $remove,
        private RelationshipOps $relationships,
        private ResultBuilder $results,
        private FlushManager $flushManager,
    ) {
    }

    /**
     * @param list<Operation> $operations
     *
     * @return array{0: list<array<string, mixed>|\stdClass>, 1: bool}
     */
    public function run(array $operations, LidRegistry $lids): array
    {
        return $this->transaction->run(function () use ($operations, $lids) {
            $resultSet = [];
            $allEmpty = true;

            foreach ($operations as $operation) {
                try {
                    if ($operation->isRelationshipOperation()) {
                        $outcome = $this->relationships->handle($operation, $lids);
                    } else {
                        $outcome = match ($operation->op) {
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
                    [$snapshot, $empty] = $this->results->build([$operation], [$outcome]);
                    $resultSet[] = $snapshot[0];
                    $allEmpty = $allEmpty && $empty;
                } catch (\AlexFigures\JsonApi\Http\Exception\JsonApiHttpException $exception) {
                    throw \AlexFigures\JsonApi\Http\Error\AtomicErrorRebaser::rebase($exception, $operation->pointer);
                }
            }

            return [$resultSet, $allEmpty];
        }, $operations);
    }
}
