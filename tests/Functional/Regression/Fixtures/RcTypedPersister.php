<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Functional\Regression\Fixtures;

use AlexFigures\JsonApi\Contract\Data\ChangeSet;
use AlexFigures\JsonApi\Contract\Data\TypedResourcePersister;

final class RcTypedPersister implements TypedResourcePersister
{
    public function supports(string $type): bool
    {
        return $type === 'rc-memory';
    }
    public function create(string $type, ChangeSet $changes, ?string $clientId = null): object
    {
        return new RcMemory($clientId ?? 'typed', 'Typed: ' . $changes->attributes['title']);
    }
    public function update(string $type, string $id, ChangeSet $changes): object
    {
        return new RcMemory($id, 'Updated: ' . $changes->attributes['title']);
    }
    public function delete(string $type, string $id): void
    {
    }
}
