<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Functional\Regression;

use AlexFigures\JsonApi\Contract\Tx\TransactionManager;

final class RcRelationshipTransaction implements TransactionManager, \AlexFigures\JsonApi\Contract\Data\WriteConcurrencyGuardInterface
{
    public int $calls = 0;
    public int $protections = 0;

    public function protect(string $type, string $id, callable $write): mixed
    {
        ++$this->protections;
        return $write();
    }

    public function transactional(callable $callback): mixed
    {
        ++$this->calls;
        return $callback();
    }
}
