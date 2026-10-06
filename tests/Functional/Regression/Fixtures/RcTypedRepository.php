<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Functional\Regression\Fixtures;

use AlexFigures\Symfony\Contract\Data\Slice;
use AlexFigures\Symfony\Contract\Data\TypedResourceRepository;
use AlexFigures\Symfony\Query\Criteria;

final class RcTypedRepository implements TypedResourceRepository
{
    public function supports(string $type): bool
    {
        return $type === 'rc-memory';
    }

    public function findCollection(string $type, Criteria $criteria): Slice
    {
        return new Slice([new RcMemory('stored', 'Stored')], 1, 20, 1);
    }
    public function findOne(string $type, string $id, Criteria $criteria): ?object
    {
        return new RcMemory($id, 'Stored');
    }
    public function findRelated(string $type, string $relationship, array $identifiers): iterable
    {
        return [];
    }
}
