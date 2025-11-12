<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Fixtures\Entity;

use AlexFigures\Symfony\Resource\Attribute\Attribute;
use AlexFigures\Symfony\Resource\Attribute\Id;
use AlexFigures\Symfony\Resource\Attribute\JsonApiResource;
use AlexFigures\Symfony\Resource\Attribute\Relationship;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

/**
 * Author entity for testing with ArticleWithSpecialTags.
 * Separate from regular Author to avoid type conflicts.
 */
#[ORM\Entity]
#[ORM\Table(name: 'authors_for_special_tags')]
#[JsonApiResource(type: 'authors-for-special-tags')]
class AuthorForSpecialTags
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
     * @var Collection<int, ArticleWithSpecialTags>
     */
    #[ORM\OneToMany(targetEntity: ArticleWithSpecialTags::class, mappedBy: 'author')]
    #[Relationship(toMany: true, targetType: 'articles-with-special-tags')]
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
     * @return Collection<int, ArticleWithSpecialTags>
     */
    public function getArticles(): Collection
    {
        return $this->articles;
    }

    public function addArticle(ArticleWithSpecialTags $article): self
    {
        if (!$this->articles->contains($article)) {
            $this->articles->add($article);
            $article->setAuthor($this);
        }
        return $this;
    }

    public function removeArticle(ArticleWithSpecialTags $article): self
    {
        $this->articles->removeElement($article);
        return $this;
    }
}
