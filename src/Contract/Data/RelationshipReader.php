<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Contract\Data;

use JsonApi\Symfony\Query\Criteria;
use JsonApi\Symfony\Query\Pagination;

interface RelationshipReader
{
    public function getToOneId(string $type, string $id, string $rel): ?string;

    public function getToManyIds(string $type, string $id, string $rel, Pagination $pagination): SliceIds;

    public function getRelatedResource(string $type, string $id, string $rel): ?object;

    public function getRelatedCollection(string $type, string $id, string $rel, Criteria $criteria): Slice;
}
