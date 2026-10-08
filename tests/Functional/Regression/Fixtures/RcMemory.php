<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Functional\Regression\Fixtures;

use AlexFigures\JsonApi\Resource\Attribute\Attribute;
use AlexFigures\JsonApi\Resource\Attribute\Id;
use AlexFigures\JsonApi\Resource\Attribute\JsonApiResource;

#[JsonApiResource(type: 'rc-memory')]
#[\AlexFigures\JsonApi\Profile\Attribute\Auditable(createdByField: 'createdBy', updatedByField: 'updatedBy')]
final class RcMemory
{
    public \DateTimeImmutable $createdAt;
    public \DateTimeImmutable $updatedAt;
    public ?string $createdBy = null;
    public ?string $updatedBy = null;

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function __construct(#[Id] public string $id, #[Attribute] public string $title)
    {
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable('2020-01-01');
    }
}
