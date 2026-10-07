<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Bridge\Symfony\Locator;

use AlexFigures\Symfony\Contract\Data\RelationshipReader;
use AlexFigures\Symfony\Contract\Data\Slice;
use AlexFigures\Symfony\Contract\Data\SliceIds;
use AlexFigures\Symfony\Contract\Data\TypedRelationshipReader;
use AlexFigures\Symfony\Query\Criteria;
use AlexFigures\Symfony\Query\Pagination;

/** @internal Dispatch endpoint reads by source resource type. */
final readonly class RelationshipReaderLocator implements RelationshipReader
{
    /** @param iterable<TypedRelationshipReader> $readers */
    public function __construct(private iterable $readers, private RelationshipReader $fallback)
    {
    }

    public function getToOneId(string $type, string $id, string $rel): ?string
    {
        return $this->reader($type)->getToOneId($type, $id, $rel);
    }
    public function getToManyIds(string $type, string $id, string $rel, Pagination $pagination): SliceIds
    {
        return $this->reader($type)->getToManyIds($type, $id, $rel, $pagination);
    }
    public function getRelatedResource(string $type, string $id, string $rel): ?object
    {
        return $this->reader($type)->getRelatedResource($type, $id, $rel);
    }
    public function getRelatedCollection(string $type, string $id, string $rel, Criteria $criteria): Slice
    {
        return $this->reader($type)->getRelatedCollection($type, $id, $rel, $criteria);
    }
    private function reader(string $type): RelationshipReader
    {
        foreach ($this->readers as $reader) {
            if ($reader->supports($type)) {
                return $reader;
            }
        }
        return $this->fallback;
    }
}
