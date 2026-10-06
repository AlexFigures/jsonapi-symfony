<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Functional\Regression\Fixtures;

use AlexFigures\Symfony\Resource\Attribute\Attribute;
use AlexFigures\Symfony\Resource\Attribute\Id;
use AlexFigures\Symfony\Resource\Attribute\JsonApiResource;

#[JsonApiResource(type: 'rc-memory')]
#[\AlexFigures\Symfony\Profile\Attribute\Auditable(createdByField: 'createdBy', updatedByField: 'updatedBy')]
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
