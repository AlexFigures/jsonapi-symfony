<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Atomic\Execution\Handlers;

use AlexFigures\Symfony\Atomic\Execution\OperationOutcome;
use AlexFigures\Symfony\Atomic\Lid\LidRegistry;
use AlexFigures\Symfony\Atomic\Operation;
use AlexFigures\Symfony\Contract\Data\ResourceProcessor;
use AlexFigures\Symfony\Http\Error\ErrorMapper;
use AlexFigures\Symfony\Http\Exception\BadRequestException;

final class RemoveHandler
{
    public function __construct(
        private readonly ResourceProcessor $processor,
        private readonly ErrorMapper $errors,
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
