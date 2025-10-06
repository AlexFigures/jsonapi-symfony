<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Invalidation;

final class NullPurger implements SurrogatePurgerInterface
{
    public function purge(array $keys): void
    {
        // no-op
    }
}
