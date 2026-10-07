<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Fixtures\Entity;

use AlexFigures\Symfony\Profile\Attribute\Auditable;
use AlexFigures\Symfony\Resource\Attribute\Attribute;
use AlexFigures\Symfony\Resource\Attribute\Id;
use AlexFigures\Symfony\Resource\Attribute\JsonApiResource;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'renamed_audit_records')]
#[JsonApiResource(type: 'renamed-audit', denormalizationContext: ['groups' => ['audit:write']])]
#[Auditable(createdAtField: 'insertedAt', updatedAtField: 'changedAt', createdByField: 'insertedBy', updatedByField: 'changedBy')]
class RenamedAuditRecord
{
    #[ORM\Id, ORM\Column, Id]
    public string $id = 'audit';
    #[ORM\Column, Attribute]
    #[\Symfony\Component\Serializer\Attribute\Groups(['audit:write'])]
    public string $title = '';
    #[ORM\Column(type: 'datetime_immutable', nullable: true), Attribute]
    public ?\DateTimeImmutable $insertedAt = null;
    #[ORM\Column(type: 'datetime_immutable', nullable: true), Attribute]
    public ?\DateTimeImmutable $changedAt = null;
    #[ORM\Column(nullable: true), Attribute]
    public ?string $insertedBy = null;
    #[ORM\Column(nullable: true), Attribute]
    public ?string $changedBy = null;
}
