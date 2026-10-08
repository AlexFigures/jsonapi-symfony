<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Atomic\Execution\Handlers;

use AlexFigures\JsonApi\Atomic\Execution\OperationOutcome;
use AlexFigures\JsonApi\Atomic\Lid\LidRegistry;
use AlexFigures\JsonApi\Atomic\Operation;
use AlexFigures\JsonApi\Contract\Data\ResourceProcessor;
use AlexFigures\JsonApi\Http\Error\ErrorMapper;
use AlexFigures\JsonApi\Http\Exception\BadRequestException;

/** @internal */
final readonly class RemoveHandler
{
    public function __construct(
        private ResourceProcessor $processor,
        private ErrorMapper $errors,
    ) {
    }

    public function handle(Operation $operation, LidRegistry $lids): OperationOutcome
    {
        $ref = $operation->ref;
        if ($ref === null) {
            throw new BadRequestException('Remove operations require a ref.');
        }

        $id = $ref->id;
        if ($id === null && $ref->lid !== null) {
            $id = $lids->resolveId($ref->lid);
            if ($id === null) {
                throw new BadRequestException('Unknown local identifier.', [
                    $this->errors->invalidPointer($operation->pointer . '/ref/lid', sprintf('Local identifier "%s" is not registered.', $ref->lid)),
                ]);
            }
        }

        if ($id === null) {
            throw new BadRequestException('Remove operations require an identifier.', [
                $this->errors->invalidPointer($operation->pointer . '/ref', 'Remove operations MUST specify a resource identifier.'),
            ]);
        }

        $this->processor->processDelete($ref->type, $id);

        return OperationOutcome::empty();
    }
}
