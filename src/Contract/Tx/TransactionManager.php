<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Contract\Tx;

interface TransactionManager
{
    /**
     * @template T
     *
     * @param callable():T $callback
     *
     * @return T
     */
    public function transactional(callable $callback);
}
