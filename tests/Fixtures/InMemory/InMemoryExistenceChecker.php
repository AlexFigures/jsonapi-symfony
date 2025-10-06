<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Fixtures\InMemory;

use JsonApi\Symfony\Contract\Data\ExistenceChecker;

final class InMemoryExistenceChecker implements ExistenceChecker
{
    public function __construct(private readonly InMemoryRepository $repository)
    {
    }

    public function exists(string $type, string $id): bool
    {
        return $this->repository->has($type, $id);
    }
}
