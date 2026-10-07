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
        return in_array($type, ['rc-memory', 'rc-routed', 'rc-tagged'], true);
    }

    public function findCollection(string $type, Criteria $criteria): Slice
    {
        return new Slice([$this->findOne($type, 'stored', $criteria)], 1, 20, 1);
    }
    public function findOne(string $type, string $id, Criteria $criteria): ?object
    {
        if ($type === 'rc-tagged') {
            return new \AlexFigures\Symfony\Tests\Functional\Regression\RcTaggedResource();
        }
        return $type === 'rc-routed' ? new RcRoutedMemory($id, 'Routed') : new RcMemory($id, 'Stored');
    }
    public function findRelated(string $type, string $relationship, array $identifiers): iterable
    {
        return [];
    }
}
