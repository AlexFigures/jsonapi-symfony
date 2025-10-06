<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Atomic\Execution\Handlers;

use JsonApi\Symfony\Atomic\Execution\OperationOutcome;
use JsonApi\Symfony\Atomic\Lid\LidRegistry;
use JsonApi\Symfony\Atomic\Operation;
use JsonApi\Symfony\Http\Error\ErrorMapper;
use JsonApi\Symfony\Http\Exception\BadRequestException;
use JsonApi\Symfony\Contract\Data\ResourcePersister;

final class RemoveHandler
{
    public function __construct(
        private readonly ResourcePersister $persister,
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

        $this->persister->delete($ref->type, $id);

        return OperationOutcome::empty();
    }
}
