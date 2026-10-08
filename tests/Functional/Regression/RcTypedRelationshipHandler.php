<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Functional\Regression;

use AlexFigures\JsonApi\Contract\Data\ResourceIdentifier;
use AlexFigures\JsonApi\Contract\Data\Slice;
use AlexFigures\JsonApi\Contract\Data\SliceIds;
use AlexFigures\JsonApi\Contract\Data\TypedRelationshipReader;
use AlexFigures\JsonApi\Contract\Data\TypedRelationshipUpdater;
use AlexFigures\JsonApi\Query\Criteria;
use AlexFigures\JsonApi\Query\Pagination;
use AlexFigures\JsonApi\Tests\Functional\Regression\Fixtures\RcMemory;

final class RcTypedRelationshipHandler implements TypedRelationshipReader, TypedRelationshipUpdater
{
    /** @var list<string> */
    public array $writes = [];
    /** @var list<string> */
    public array $reads = [];
    public function supports(string $type): bool
    {
        return $type === 'rc-tagged';
    }
    public function getToOneId(string $type, string $id, string $rel): ?string
    {
        $this->reads[] = 'linkage-one';
        return 'stored';
    }
    public function getToManyIds(string $type, string $id, string $rel, Pagination $pagination): SliceIds
    {
        $this->reads[] = 'linkage-many';
        return new SliceIds(['stored'], $pagination->number, $pagination->size, 1);
    }
    public function getRelatedResource(string $type, string $id, string $rel): ?object
    {
        $this->reads[] = 'related-one';
        return new RcMemory('stored', 'Typed relation');
    }
    public function getRelatedCollection(string $type, string $id, string $rel, Criteria $criteria): Slice
    {
        $this->reads[] = 'related-many';
        return new Slice([$this->getRelatedResource($type, $id, $rel)], $criteria->pagination->number, $criteria->pagination->size, 1);
    }
    public function replaceToOne(string $type, string $id, string $rel, ?ResourceIdentifier $target): void
    {
        $this->writes[] = 'replace-one';
    }
    public function replaceToMany(string $type, string $id, string $rel, array $targets): void
    {
        $this->writes[] = 'replace-many';
    }
    public function addToMany(string $type, string $id, string $rel, array $targets): void
    {
        $this->writes[] = 'add-many';
    }
    public function removeFromToMany(string $type, string $id, string $rel, array $targets): void
    {
        $this->writes[] = 'remove-many';
    }
}
