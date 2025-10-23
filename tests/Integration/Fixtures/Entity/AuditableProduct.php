<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Fixtures\Entity;

use AlexFigures\Symfony\Profile\Attribute\Auditable;
use AlexFigures\Symfony\Resource\Attribute\Attribute;
use AlexFigures\Symfony\Resource\Attribute\Id;
use AlexFigures\Symfony\Resource\Attribute\JsonApiResource;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

/**
 * Test entity with Auditable attribute for profile validation testing.
 */
#[ORM\Entity]
#[ORM\Table(name: 'auditable_products')]
#[JsonApiResource(
    type: 'auditable-products',
    normalizationContext: ['groups' => ['auditable_product:read']],
    denormalizationContext: ['groups' => ['auditable_product:write']],
)]
#[Auditable(
    createdAtField: 'createdAt',
    updatedAtField: 'updatedAt',
    createdByField: 'createdBy',
    updatedByField: 'updatedBy'
)]
class AuditableProduct
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    #[Id]
    #[Attribute]
    #[Groups(['auditable_product:read'])]
    private string $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Attribute]
    #[Groups(['auditable_product:read', 'auditable_product:write'])]
    private string $name;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Attribute]
    #[Groups(['auditable_product:read', 'auditable_product:write'])]
    private string $price;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Attribute]
    #[Groups(['auditable_product:read'])]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Attribute]
    #[Groups(['auditable_product:read'])]
    private DateTimeImmutable $updatedAt;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Attribute]
    #[Groups(['auditable_product:read'])]
    private ?string $createdBy = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Attribute]
    #[Groups(['auditable_product:read'])]
    private ?string $updatedBy = null;

    public function __construct()
    {
        $this->id = Uuid::v4()->toRfc4122();
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function setPrice(string $price): void
    {
        $this->price = $price;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getCreatedBy(): ?string
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?string $createdBy): void
    {
        $this->createdBy = $createdBy;
    }

    public function getUpdatedBy(): ?string
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?string $updatedBy): void
    {
        $this->updatedBy = $updatedBy;
    }
}
