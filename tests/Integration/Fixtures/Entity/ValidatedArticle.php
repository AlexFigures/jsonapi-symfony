<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Fixtures\Entity;

use AlexFigures\Symfony\Resource\Attribute\Attribute;
use AlexFigures\Symfony\Resource\Attribute\Id;
use AlexFigures\Symfony\Resource\Attribute\JsonApiResource;
use AlexFigures\Symfony\Resource\Attribute\Relationship;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Embeddable\ContactInfo;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Test entity for validation and denormalization groups testing.
 *
 * This entity has different validation constraints for create and update operations,
 * and different denormalization groups for various scenarios.
 */
#[ORM\Entity]
#[ORM\Table(name: 'validated_articles')]
#[JsonApiResource(
    type: 'validated-articles',
    normalizationContext: ['groups' => ['Default']],
    denormalizationContext: ['groups' => ['write', 'Default']],
)]
class ValidatedArticle
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    #[Id]
    #[Attribute]
    private string $id;

    /**
     * Title is required in both create and update operations.
     * Length constraint applies to both operations.
     */
    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank(groups: ['create', 'update', 'Default'])]
    #[Assert\Length(min: 3, max: 255, groups: ['create', 'update', 'Default'])]
    #[Groups(['write', 'create', 'update', 'Default'])]
    #[Attribute]
    private string $title;

    /**
     * Content is required only on create, optional on update.
     */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\NotBlank(groups: ['create'])]
    #[Assert\Length(min: 10, groups: ['create', 'update'])]
    #[Groups(['write', 'create', 'update', 'Default'])]
    #[Attribute]
    private ?string $content = null;

    /**
     * Contact email is validated only on update operations.
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Assert\Email(groups: ['update'])]
    #[Groups(['write', 'update', 'Default'])]
    #[Attribute]
    private ?string $contactEmail = null;



    /**
     * Status with custom validation group.
     */
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    #[Assert\Choice(choices: ['draft', 'published', 'archived'], groups: ['status_validation'])]
    #[Groups(['write', 'Default'])]
    #[Attribute]
    private ?string $status = 'draft';

    /**
     * Priority - integer field for type validation testing.
     */
    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Range(min: 1, max: 10, groups: ['Default'])]
    #[Groups(['write', 'Default'])]
    #[Attribute]
    private ?int $priority = null;

    /**
     * Published date for format validation testing.
     */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['write', 'Default'])]
    #[Attribute]
    private ?\DateTimeImmutable $publishedAt = null;

    /**
     * Author relationship - optional, but can be validated with custom groups.
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'id', nullable: true)]
    #[Assert\NotNull(groups: ['require_author'], message: 'Author is required')]
    #[Groups(['write', 'Default'])]
    #[Relationship(targetType: 'users')]
    private ?User $author = null;

    /**
     * Category relationship - nullable, can be set to null during update.
     */
    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: true)]
    #[Groups(['write', 'Default'])]
    #[Relationship(targetType: 'categories')]
    private ?Category $category = null;

    /**
     * Contact information - embeddable value object for testing nested validation.
     */
    #[ORM\Embedded(class: ContactInfo::class)]
    #[Assert\Valid]
    #[Groups(['write', 'Default'])]
    #[Attribute]
    private ?ContactInfo $contactInfo = null;

    public function __construct(string $title)
    {
        $this->id = \Symfony\Component\Uid\Uuid::v7()->toString();
        $this->title = $title;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): void
    {
        $this->content = $content;
    }

    public function getContactEmail(): ?string
    {
        return $this->contactEmail;
    }

    public function setContactEmail(?string $contactEmail): void
    {
        $this->contactEmail = $contactEmail;
    }



    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): void
    {
        $this->status = $status;
    }

    public function getPriority(): ?int
    {
        return $this->priority;
    }

    public function setPriority(?int $priority): void
    {
        $this->priority = $priority;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): void
    {
        $this->publishedAt = $publishedAt;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): void
    {
        $this->author = $author;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): void
    {
        $this->category = $category;
    }

    public function getContactInfo(): ?ContactInfo
    {
        return $this->contactInfo;
    }

    public function setContactInfo(?ContactInfo $contactInfo): void
    {
        $this->contactInfo = $contactInfo;
    }
}
