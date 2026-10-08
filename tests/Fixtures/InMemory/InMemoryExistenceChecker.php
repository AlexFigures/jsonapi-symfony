<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Fixtures\InMemory;

use AlexFigures\JsonApi\Contract\Data\ExistenceChecker;

final readonly class InMemoryExistenceChecker implements ExistenceChecker
{
    public function __construct(private InMemoryRepository $repository)
    {
    }

    public function exists(string $type, string $id): bool
    {
        return $this->repository->has($type, $id);
    }
}
