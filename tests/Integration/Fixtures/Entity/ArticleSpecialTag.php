<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Join table entity for Article-SpecialTag relationship with additional metadata.
 *
 * This entity represents a many-to-many relationship between Article and SpecialTag
 * with extra fields (e.g., addedBy). It is NOT exposed as a JSON:API resource,
 * but is used internally for navigation via propertyPath aliases.
 */
#[ORM\Entity]
#[ORM\Table(name: 'article_special_tags')]
class ArticleSpecialTag
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    /**
     * @var Article|ArticleWithSpecialTags
     */
    #[ORM\ManyToOne(targetEntity: Article::class, inversedBy: 'articleSpecialTags')]
    #[ORM\JoinColumn(name: 'article_id', referencedColumnName: 'id', nullable: false)]
    private object $article;

    #[ORM\ManyToOne(targetEntity: SpecialTag::class)]
    #[ORM\JoinColumn(name: 'special_tag_id', referencedColumnName: 'id', nullable: false)]
    private SpecialTag $specialTag;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $addedBy = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v4()->toRfc4122();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return Article|ArticleWithSpecialTags
     */
    public function getArticle(): object
    {
        return $this->article;
    }

    public function setArticle(Article|ArticleWithSpecialTags $article): self
    {
        $this->article = $article;
        return $this;
    }

    public function getSpecialTag(): SpecialTag
    {
        return $this->specialTag;
    }

    public function setSpecialTag(SpecialTag $specialTag): self
    {
        $this->specialTag = $specialTag;
        return $this;
    }

    public function getAddedBy(): ?string
    {
        return $this->addedBy;
    }

    public function setAddedBy(?string $addedBy): self
    {
        $this->addedBy = $addedBy;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
