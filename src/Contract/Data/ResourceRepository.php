<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Contract\Data;

use JsonApi\Symfony\Query\Criteria;

interface ResourceRepository
{
    public function findCollection(string $type, Criteria $criteria): Slice;

    public function findOne(string $type, string $id, Criteria $criteria): ?object;

    /**
     * @param list<ResourceIdentifier> $identifiers
     *
     * @return iterable<object>
     */
    public function findRelated(string $type, string $relationship, array $identifiers): iterable;
}
