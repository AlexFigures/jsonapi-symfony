<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Invalidation;

final class InvalidationDispatcher
{
    public function __construct(private readonly SurrogatePurgerInterface $purger)
    {
    }

    /**
     * @param list<string> $keys
     */
    public function invalidate(array $keys): void
    {
        if ($keys === []) {
            return;
        }

        $this->purger->purge(array_values(array_unique($keys)));
    }
}
