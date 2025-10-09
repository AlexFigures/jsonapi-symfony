<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Integration\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use JsonApi\Symfony\Resource\Attribute\Attribute;
use JsonApi\Symfony\Resource\Attribute\FilterableFields;
use JsonApi\Symfony\Resource\Attribute\Id;
use JsonApi\Symfony\Resource\Attribute\JsonApiResource;
use JsonApi\Symfony\Resource\Attribute\SortableFields;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'tags')]
#[JsonApiResource(
    type: 'tags',
    normalizationContext: ['groups' => ['tag:read']],
    denormalizationContext: ['groups' => ['tag:write']],
)]
#[FilterableFields(['name'])]
#[SortableFields(['name'])]
class Tag
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    #[Id]
    #[Attribute]
    #[Groups(['tag:read'])]
    private string $id;

    #[ORM\Column(type: 'string', length: 100)]
    #[Attribute]
    #[Groups(['tag:read', 'tag:write'])]
    private string $name;

    public function __construct()
    {
        $this->id = Uuid::v4()->toRfc4122();
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
}

