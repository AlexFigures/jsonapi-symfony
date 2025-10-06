<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Contract\Data;

interface ExistenceChecker
{
    public function exists(string $type, string $id): bool;
}
