<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Invalidation;

/** @internal */
final class NullPurger implements SurrogatePurgerInterface
{
    public function purge(array $keys): void
    {
        // no-op
    }
}
