<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Fixtures\Relationship;

use AlexFigures\Symfony\Resource\Attribute\Id;
use AlexFigures\Symfony\Resource\Attribute\JsonApiResource;
use AlexFigures\Symfony\Resource\Attribute\Relationship;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'eager_nodes')]
#[JsonApiResource(type: 'eager-nodes')]
class EagerNode
{
    #[ORM\Id]
    #[ORM\Column(type: 'string')]
    #[Id]
    public string $id;

    #[ORM\Column(type: 'string', unique: true)]
    public string $name;

    #[ORM\ManyToOne(targetEntity: self::class, fetch: 'EAGER')]
    #[ORM\JoinColumn(nullable: true)]
    private ?self $parent = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children', fetch: 'EAGER')]
    #[ORM\JoinColumn(nullable: true)]
    #[Relationship(targetType: 'eager-nodes')]
    private ?self $mentor = null;

    /** @var Collection<int, self> */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'mentor')]
    #[Relationship(toMany: true, targetType: 'eager-nodes')]
    private Collection $children;

    public function __construct(string $id)
    {
        $this->id = $id;
        $this->name = $id;
        $this->children = new ArrayCollection();
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): void
    {
        $this->parent = $parent;
    }

    public function getMentor(): ?self
    {
        return $this->mentor;
    }

    public function setMentor(?self $mentor): void
    {
        $this->mentor = $mentor;
    }

    #[Relationship(targetType: 'eager-nodes', propertyPath: 'parent')]
    public function getGuardian(): ?self
    {
        return $this->parent;
    }

    /** @return Collection<int, self> */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(self $child): void
    {
        // Deliberately rely on the caller to avoid duplicate additions.
        $this->children->add($child);
    }

    public function removeChild(self $child): void
    {
        $this->children->removeElement($child);
    }
}
