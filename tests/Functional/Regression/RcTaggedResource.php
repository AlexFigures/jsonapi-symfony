<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Functional\Regression;

use AlexFigures\JsonApi\Resource\Attribute\Attribute;
use AlexFigures\JsonApi\Resource\Attribute\Id;
use AlexFigures\JsonApi\Resource\Attribute\JsonApiResource;

#[JsonApiResource(type: 'rc-tagged', exposeId: false)]
#[\AlexFigures\JsonApi\Profile\Attribute\Auditable(createdAtField: 'insertedAt', updatedAtField: 'changedAt', createdByField: 'insertedBy', updatedByField: 'changedBy')]
final class RcTaggedResource
{
    #[Id]
    public string $id = 'stored';
    #[Attribute]
    public string $title = 'Tagged';
    public \DateTimeImmutable $insertedAt;
    public \DateTimeImmutable $changedAt;
    public ?string $insertedBy = null;
    public ?string $changedBy = null;
    #[\AlexFigures\JsonApi\Resource\Attribute\Relationship(targetType: 'rc-memory')]
    public ?\AlexFigures\JsonApi\Tests\Functional\Regression\Fixtures\RcMemory $one = null;
    /** @var list<\AlexFigures\JsonApi\Tests\Functional\Regression\Fixtures\RcMemory> */
    #[\AlexFigures\JsonApi\Resource\Attribute\Relationship(toMany: true, targetType: 'rc-memory')]
    public array $many = [];
}
