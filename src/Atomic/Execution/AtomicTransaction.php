<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Atomic\Execution;

use AlexFigures\Symfony\Contract\Tx\TransactionManager;

final class AtomicTransaction
{
    public function __construct(
        private readonly TransactionManager $transactions,
        private readonly ?\AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface $resources = null,
        private readonly ?\AlexFigures\Symfony\Http\Write\InputDocumentValidator $inputValidator = null,
    ) {
    }

    /**
     * @template T
     *
     * @param callable():T                                $callback
     * @param list<\AlexFigures\Symfony\Atomic\Operation> $operations
     *
     * @return T
     */
    public function run(callable $callback, array $operations = [])
    {
        if ($this->resources !== null) {
            if ($this->transactions instanceof \AlexFigures\Symfony\Contract\Tx\ScopedTransactionManagerInterface) {
                $validator = $this->inputValidator ?? new \AlexFigures\Symfony\Http\Write\InputDocumentValidator(
                    $this->resources,
                    new \AlexFigures\Symfony\Http\Write\WriteConfig(true),
                    new \AlexFigures\Symfony\Http\Error\ErrorMapper(new \AlexFigures\Symfony\Http\Error\ErrorBuilder(false)),
                );
                // Validate linkage types before boundary lookup; malformed identifiers must not
                // become unknown-metadata server errors or enlist an unrelated manager.
                foreach ($operations as $operation) {
                    if ($operation->op === 'remove' || $operation->isRelationshipOperation() || $operation->ref === null) {
                        continue;
                    }
                    try {
                        $validator->validateAndExtract($operation->ref->type, null, ['data' => $operation->data], $operation->op === 'add' ? 'POST' : 'ATOMIC_UPDATE', true);
                    } catch (\AlexFigures\Symfony\Http\Exception\JsonApiHttpException $exception) {
                        throw \AlexFigures\Symfony\Http\Error\AtomicErrorRebaser::rebase($exception, $operation->pointer);
                    }
                }
            }
            return \AlexFigures\Symfony\Tx\TransactionScope::run($this->transactions, $this->resources, AtomicResourceTypes::collect($operations), $callback);
        }
        if ($operations !== [] && $this->transactions instanceof \AlexFigures\Symfony\Contract\Tx\ScopedTransactionManagerInterface) {
            throw new \LogicException('Scoped Atomic execution requires a resource registry for transaction preflight.');
        }
        return $this->transactions->transactional($callback);
    }
}
