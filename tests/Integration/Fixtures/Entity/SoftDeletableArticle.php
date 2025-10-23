<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Fixtures\Entity;

use AlexFigures\Symfony\Profile\Attribute\SoftDeletable;
use AlexFigures\Symfony\Resource\Attribute\Attribute;
use AlexFigures\Symfony\Resource\Attribute\Id;
use AlexFigures\Symfony\Resource\Attribute\JsonApiResource;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

/**
 * Test entity with SoftDeletable attribute for profile validation testing.
 */
#[ORM\Entity]
#[ORM\Table(name: 'soft_deletable_articles')]
#[JsonApiResource(
    type: 'soft-deletable-articles',
    normalizationContext: ['groups' => ['soft_article:read']],
    denormalizationContext: ['groups' => ['soft_article:write']],
)]
#[SoftDeletable(deletedAtField: 'deletedAt', deletedByField: 'deletedBy')]
class SoftDeletableArticle
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    #[Id]
    #[Attribute]
    #[Groups(['soft_article:read'])]
    private string $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Attribute]
    #[Groups(['soft_article:read', 'soft_article:write'])]
    private string $title;

    #[ORM\Column(type: 'text')]
    #[Attribute]
    #[Groups(['soft_article:read', 'soft_article:write'])]
    private string $content;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Attribute]
    #[Groups(['soft_article:read'])]
    private ?DateTimeImmutable $deletedAt = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Attribute]
    #[Groups(['soft_article:read'])]
    private ?string $deletedBy = null;

    public function __construct()
    {
        $this->id = Uuid::v4()->toRfc4122();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?DateTimeImmutable $deletedAt): void
    {
        $this->deletedAt = $deletedAt;
    }

    public function getDeletedBy(): ?string
    {
        return $this->deletedBy;
    }

    public function setDeletedBy(?string $deletedBy): void
    {
        $this->deletedBy = $deletedBy;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function softDelete(?string $deletedBy = null): void
    {
        $this->deletedAt = new DateTimeImmutable();
        $this->deletedBy = $deletedBy;
    }

    public function restore(): void
    {
        $this->deletedAt = null;
        $this->deletedBy = null;
    }
}
