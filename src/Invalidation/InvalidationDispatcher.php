<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Invalidation;

/** @internal */
final readonly class InvalidationDispatcher
{
    public function __construct(private SurrogatePurgerInterface $purger)
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
