<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Atomic\Execution;

use AlexFigures\JsonApi\Contract\Tx\TransactionManager;

/** @internal */
final readonly class AtomicTransaction
{
    public function __construct(
        private TransactionManager $transactions,
        private ?\AlexFigures\JsonApi\Resource\Registry\ResourceRegistryInterface $resources = null,
        private ?\AlexFigures\JsonApi\Http\Write\InputDocumentValidator $inputValidator = null,
    ) {
    }

    /**
     * @template T
     *
     * @param callable():T                                $callback
     * @param list<\AlexFigures\JsonApi\Atomic\Operation> $operations
     *
     * @return T
     */
    public function run(callable $callback, array $operations = [])
    {
        if ($this->resources !== null) {
            if ($this->transactions instanceof \AlexFigures\JsonApi\Contract\Tx\ScopedTransactionManagerInterface) {
                $validator = $this->inputValidator ?? new \AlexFigures\JsonApi\Http\Write\InputDocumentValidator(
                    $this->resources,
                    new \AlexFigures\JsonApi\Http\Write\WriteConfig(true),
                    new \AlexFigures\JsonApi\Http\Error\ErrorMapper(new \AlexFigures\JsonApi\Http\Error\ErrorBuilder(false)),
                );
                // Validate linkage types before boundary lookup; malformed identifiers must not
                // become unknown-metadata server errors or enlist an unrelated manager.
                foreach ($operations as $operation) {
                    if ($operation->op === 'remove' || $operation->isRelationshipOperation() || $operation->ref === null) {
                        continue;
                    }
                    try {
                        $validator->validateAndExtract($operation->ref->type, null, ['data' => $operation->data], $operation->op === 'add' ? 'POST' : 'ATOMIC_UPDATE', true);
                    } catch (\AlexFigures\JsonApi\Http\Exception\JsonApiHttpException $exception) {
                        throw \AlexFigures\JsonApi\Http\Error\AtomicErrorRebaser::rebase($exception, $operation->pointer);
                    }
                }
            }
            return \AlexFigures\JsonApi\Tx\TransactionScope::run($this->transactions, $this->resources, AtomicResourceTypes::collect($operations), $callback);
        }
        if ($operations !== [] && $this->transactions instanceof \AlexFigures\JsonApi\Contract\Tx\ScopedTransactionManagerInterface) {
            throw new \LogicException('Scoped Atomic execution requires a resource registry for transaction preflight.');
        }
        return $this->transactions->transactional($callback);
    }
}
