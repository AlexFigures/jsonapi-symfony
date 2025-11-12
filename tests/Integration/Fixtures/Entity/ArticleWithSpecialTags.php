<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Fixtures\Entity;

use AlexFigures\Symfony\Resource\Attribute\Attribute;
use AlexFigures\Symfony\Resource\Attribute\FilterableField;
use AlexFigures\Symfony\Resource\Attribute\FilterableFields;
use AlexFigures\Symfony\Resource\Attribute\Id;
use AlexFigures\Symfony\Resource\Attribute\JsonApiResource;
use AlexFigures\Symfony\Resource\Attribute\Relationship;
use AlexFigures\Symfony\Resource\Attribute\SortableField;
use AlexFigures\Symfony\Resource\Attribute\SortableFields;
use AlexFigures\Symfony\Resource\Metadata\RelationshipLinkingPolicy;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

/**
 * Article entity for testing propertyPath aliases.
 * This entity adds the specialTags relationship with propertyPath.
 *
 * Uses a separate database table for complete isolation from regular Article tests.
 */
#[ORM\Entity]
#[ORM\Table(name: 'articles_with_special_tags')]
#[ORM\HasLifecycleCallbacks]
#[JsonApiResource(
    type: 'articles-with-special-tags',
    normalizationContext: ['groups' => ['article:read']],
    denormalizationContext: ['groups' => ['article:write']],
)]
#[FilterableFields([
    'title',
    new FilterableField('author', inherit: true),
    'tags.id',
    new FilterableField('specialTags', inherit: true),  // Test propertyPath alias
])]
#[SortableFields([
    'title',
    'createdAt',
    'updatedAt',
    'viewCount',
    new SortableField('author', inherit: true),
    new SortableField('specialTags', inherit: true),  // Test propertyPath alias
])]
class ArticleWithSpecialTags
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    #[Id]
    #[Attribute]
    #[Groups(['article:read'])]
    private string $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Attribute]
    #[Groups(['article:read', 'article:write'])]
    private string $title;

    #[ORM\Column(type: 'text')]
    #[Attribute]
    #[Groups(['article:read', 'article:write'])]
    private string $content;

    #[ORM\Column(enumType: ArticleStatus::class)]
    #[Attribute]
    #[Groups(['article:read', 'article:write'])]
    private ArticleStatus $status = ArticleStatus::DRAFT;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Attribute]
    #[Groups(['article:read'])]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Attribute]
    #[Groups(['article:read'])]
    private ?DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: AuthorForSpecialTags::class, inversedBy: 'articles')]
    #[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'id', nullable: true)]
    #[Relationship(targetType: 'authors-for-special-tags', linkingPolicy: RelationshipLinkingPolicy::VERIFY)]
    private ?AuthorForSpecialTags $author = null;

    /**
     * @var Collection<int, Tag>
     */
    #[ORM\ManyToMany(targetEntity: Tag::class)]
    #[ORM\JoinTable(name: 'article_with_special_tags_tags')]
    #[ORM\JoinColumn(name: 'article_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'tag_id', referencedColumnName: 'id')]
    #[Relationship(toMany: true, targetType: 'tags', linkingPolicy: RelationshipLinkingPolicy::VERIFY)]
    private Collection $tags;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Attribute]
    #[Groups(['article:read'])]
    private int $viewCount = 0;

    /**
     * @var Collection<int, ArticleWithSpecialTagsSpecialTag>
     */
    #[ORM\OneToMany(targetEntity: ArticleWithSpecialTagsSpecialTag::class, mappedBy: 'article', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $articleSpecialTags;

    /**
     * Internal relationship to SpecialTag - this is the real Doctrine property.
     * We'll expose this via an alias 'specialTags' in the API.
     *
     * @var Collection<int, SpecialTag>
     */
    #[ORM\ManyToMany(targetEntity: SpecialTag::class)]
    #[ORM\JoinTable(
        name: 'articles_with_special_tags_special_tags',
        joinColumns: [new ORM\JoinColumn(name: 'article_id', referencedColumnName: 'id')],
        inverseJoinColumns: [new ORM\JoinColumn(name: 'special_tag_id', referencedColumnName: 'id')]
    )]
    private Collection $internalSpecialTags;



    public function __construct()
    {
        $this->id = Uuid::v4()->toRfc4122();
        $this->tags = new ArrayCollection();
        $this->articleSpecialTags = new ArrayCollection();
        $this->internalSpecialTags = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new DateTimeImmutable();
        }
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new DateTimeImmutable();
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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getStatus(): ArticleStatus
    {
        return $this->status;
    }

    public function setStatus(ArticleStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getAuthor(): ?AuthorForSpecialTags
    {
        return $this->author;
    }

    public function setAuthor(?AuthorForSpecialTags $author): self
    {
        $this->author = $author;
        return $this;
    }

    /**
     * @return Collection<int, Tag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): self
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }
        return $this;
    }

    public function removeTag(Tag $tag): self
    {
        $this->tags->removeElement($tag);
        return $this;
    }

    public function clearTags(): self
    {
        $this->tags->clear();
        return $this;
    }

    public function getViewCount(): int
    {
        return $this->viewCount;
    }

    public function setViewCount(int $viewCount): self
    {
        $this->viewCount = $viewCount;
        return $this;
    }

    /**
     * @return Collection<int, ArticleWithSpecialTagsSpecialTag>
     */
    public function getArticleSpecialTags(): Collection
    {
        return $this->articleSpecialTags;
    }

    public function addArticleSpecialTag(ArticleWithSpecialTagsSpecialTag $articleSpecialTag): self
    {
        if (!$this->articleSpecialTags->contains($articleSpecialTag)) {
            $this->articleSpecialTags->add($articleSpecialTag);
            $articleSpecialTag->setArticle($this);
        }
        return $this;
    }

    public function removeArticleSpecialTag(ArticleWithSpecialTagsSpecialTag $articleSpecialTag): self
    {
        $this->articleSpecialTags->removeElement($articleSpecialTag);
        return $this;
    }

    /**
     * @return Collection<int, SpecialTag>
     */
    public function getInternalSpecialTags(): Collection
    {
        return $this->internalSpecialTags;
    }

    public function addInternalSpecialTag(SpecialTag $specialTag): self
    {
        if (!$this->internalSpecialTags->contains($specialTag)) {
            $this->internalSpecialTags->add($specialTag);
        }
        return $this;
    }

    public function removeInternalSpecialTag(SpecialTag $specialTag): self
    {
        $this->internalSpecialTags->removeElement($specialTag);
        return $this;
    }

    /**
     * Virtual getter for specialTags (alias).
     * This is used by the serializer when accessing the 'specialTags' relationship.
     *
     * @return Collection<int, SpecialTag>
     */
    #[Relationship(
        toMany: true,
        targetType: 'special-tags',
        propertyPath: 'internalSpecialTags',
        linkingPolicy: RelationshipLinkingPolicy::VERIFY
    )]
    public function getSpecialTags(): Collection
    {
        return $this->internalSpecialTags;
    }
}
