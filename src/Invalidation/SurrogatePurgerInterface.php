<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Invalidation;

/** @api */
interface SurrogatePurgerInterface
{
    /**
     * @param list<string> $keys
     */
    public function purge(array $keys): void;
}
