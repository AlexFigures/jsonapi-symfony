<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Invalidation;

interface SurrogatePurgerInterface
{
    /**
     * @param list<string> $keys
     */
    public function purge(array $keys): void;
}
