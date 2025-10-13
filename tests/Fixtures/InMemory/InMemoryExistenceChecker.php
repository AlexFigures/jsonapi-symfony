<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Fixtures\InMemory;

use AlexFigures\Symfony\Contract\Data\ExistenceChecker;

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
