<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Integration\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use JsonApi\Symfony\Resource\Attribute\Attribute;
use JsonApi\Symfony\Resource\Attribute\FilterableField;
use JsonApi\Symfony\Resource\Attribute\FilterableFields;
use JsonApi\Symfony\Resource\Attribute\Id;
use JsonApi\Symfony\Resource\Attribute\JsonApiResource;
use JsonApi\Symfony\Resource\Attribute\Relationship;
use JsonApi\Symfony\Resource\Attribute\SortableFields;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

/**
 * Test entity with underscore in resource type name (category_synonyms).
 *
 * This entity demonstrates that the JSON:API implementation correctly handles
 * resource types with underscores, which is important for testing naming
 * convention behavior (snake_case vs kebab-case).
 */
#[ORM\Entity]
#[ORM\Table(name: 'category_synonyms')]
#[ORM\UniqueConstraint(name: 'unique_name_per_category', columns: ['name', 'category_id'])]
#[JsonApiResource(
    type: 'category_synonyms',
    normalizationContext: ['groups' => ['category_synonym:read']],
    denormalizationContext: ['groups' => ['category_synonym:write']],
    description: 'Category synonyms and alternative names',
    exposeId: true,
)]
#[SortableFields(['name', 'createdAt'])]
#[FilterableFields([
    new FilterableField('name', ['eq', 'like']),
    new FilterableField('createdAt', ['gt', 'gte', 'lt', 'lte', 'eq']),
])]
class CategorySynonym
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\SequenceGenerator(sequenceName: 'category_synonyms_id_seq', allocationSize: 1, initialValue: 1)]
    #[Id]
    #[Attribute]
    #[Groups(['category_synonym:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, nullable: false)]
    #[Attribute]
    #[Groups(['category_synonym:read', 'category_synonym:write'])]
    private string $name = '';

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    #[Attribute]
    #[Groups(['category_synonym:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    #[Relationship(toMany: false, targetType: 'categories')]
    #[Groups(['category_synonym:read', 'category_synonym:write'])]
    private ?Category $category = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): self
    {
        $this->category = $category;
        return $this;
    }
}
