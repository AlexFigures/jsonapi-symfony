<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Functional\Regression;

use AlexFigures\Symfony\Contract\Data\ResourceIdentifier;
use AlexFigures\Symfony\Contract\Data\Slice;
use AlexFigures\Symfony\Contract\Data\SliceIds;
use AlexFigures\Symfony\Contract\Data\TypedRelationshipReader;
use AlexFigures\Symfony\Contract\Data\TypedRelationshipUpdater;
use AlexFigures\Symfony\Query\Criteria;
use AlexFigures\Symfony\Query\Pagination;
use AlexFigures\Symfony\Tests\Functional\Regression\Fixtures\RcMemory;

final class RcTypedRelationshipHandler implements TypedRelationshipReader, TypedRelationshipUpdater
{
    /** @var list<string> */
    public array $writes = [];
    public function supports(string $type): bool
    {
        return $type === 'rc-tagged';
    }
    public function getToOneId(string $type, string $id, string $rel): ?string
    {
        return 'stored';
    }
    public function getToManyIds(string $type, string $id, string $rel, Pagination $pagination): SliceIds
    {
        return new SliceIds(['stored'], $pagination->number, $pagination->size, 1);
    }
    public function getRelatedResource(string $type, string $id, string $rel): ?object
    {
        return new RcMemory('stored', 'Typed relation');
    }
    public function getRelatedCollection(string $type, string $id, string $rel, Criteria $criteria): Slice
    {
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
