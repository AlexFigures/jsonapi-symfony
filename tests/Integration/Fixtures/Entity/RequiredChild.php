<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Integration\Fixtures\Entity;

use AlexFigures\JsonApi\Resource\Attribute as JsonApi;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'required_children')]
#[JsonApi\JsonApiResource(type: 'required-children')]
class RequiredChild
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[JsonApi\Id]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: GeneratedRecord::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[JsonApi\Relationship(targetType: 'generated-records')]
    public ?GeneratedRecord $parent = null;
}
