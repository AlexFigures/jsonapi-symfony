<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Functional\Regression;

use AlexFigures\Symfony\Resource\Attribute\Attribute;
use AlexFigures\Symfony\Resource\Attribute\Id;
use AlexFigures\Symfony\Resource\Attribute\JsonApiResource;

#[JsonApiResource(type: 'rc-tagged', exposeId: false)]
#[\AlexFigures\Symfony\Profile\Attribute\Auditable(createdAtField: 'insertedAt', updatedAtField: 'changedAt', createdByField: 'insertedBy', updatedByField: 'changedBy')]
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
    #[\AlexFigures\Symfony\Resource\Attribute\Relationship(targetType: 'rc-memory')]
    public ?\AlexFigures\Symfony\Tests\Functional\Regression\Fixtures\RcMemory $one = null;
    /** @var list<\AlexFigures\Symfony\Tests\Functional\Regression\Fixtures\RcMemory> */
    #[\AlexFigures\Symfony\Resource\Attribute\Relationship(toMany: true, targetType: 'rc-memory')]
    public array $many = [];
}
