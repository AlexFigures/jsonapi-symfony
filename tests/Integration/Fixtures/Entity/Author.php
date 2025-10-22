<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Fixtures\Entity;

use AlexFigures\Symfony\Resource\Attribute\Attribute;
use AlexFigures\Symfony\Resource\Attribute\FilterableFields;
use AlexFigures\Symfony\Resource\Attribute\Id;
use AlexFigures\Symfony\Resource\Attribute\JsonApiResource;
use AlexFigures\Symfony\Resource\Attribute\Relationship;
use AlexFigures\Symfony\Resource\Attribute\SortableFields;
use AlexFigures\Symfony\Resource\Metadata\RelationshipLinkingPolicy;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'authors')]
#[JsonApiResource(
    type: 'authors',
    normalizationContext: ['groups' => ['author:read']],
    denormalizationContext: ['groups' => ['author:write']],
)]
#[FilterableFields(['id', 'name', 'email'])]
#[SortableFields(['name', 'email'])]
class Author
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    #[Id]
    #[Attribute]
    #[Groups(['author:read'])]
    private string $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Attribute]
    #[Groups(['author:read', 'author:write'])]
    private string $name;

    #[ORM\Column(type: 'string', length: 255)]
    #[Attribute]
    #[Groups(['author:read', 'author:write'])]
    private string $email;

    /**
     * @var Collection<int, Article>
     */
    #[ORM\OneToMany(targetEntity: Article::class, mappedBy: 'author')]
    #[Relationship(toMany: true, inverse: 'author', targetType: 'articles', linkingPolicy: RelationshipLinkingPolicy::VERIFY)]
    private Collection $articles;

    public function __construct()
    {
        $this->id = Uuid::v4()->toRfc4122();
        $this->articles = new ArrayCollection();
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    /**
     * @return Collection<int, Article>
     */
    public function getArticles(): Collection
    {
        return $this->articles;
    }

    public function addArticle(Article $article): self
    {
        if (!$this->articles->contains($article)) {
            $this->articles->add($article);
            $article->setAuthor($this);
        }
        return $this;
    }

    public function removeArticle(Article $article): self
    {
        if ($this->articles->removeElement($article)) {
            if ($article->getAuthor() === $this) {
                $article->setAuthor(null);
            }
        }
        return $this;
    }
}
