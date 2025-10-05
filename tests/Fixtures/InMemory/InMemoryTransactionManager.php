<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Fixtures\InMemory;

use JsonApi\Symfony\Contract\Tx\TransactionManager;

final class InMemoryTransactionManager implements TransactionManager
{
    public function transactional(callable $callback)
    {
        return $callback();
    }
}
