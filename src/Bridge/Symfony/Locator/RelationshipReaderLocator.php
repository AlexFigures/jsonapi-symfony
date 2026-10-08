<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Bridge\Symfony\Locator;

use AlexFigures\JsonApi\Contract\Data\RelationshipReader;
use AlexFigures\JsonApi\Contract\Data\Slice;
use AlexFigures\JsonApi\Contract\Data\SliceIds;
use AlexFigures\JsonApi\Contract\Data\TypedRelationshipReader;
use AlexFigures\JsonApi\Query\Criteria;
use AlexFigures\JsonApi\Query\Pagination;

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
