<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Fixtures\Entity;

use AlexFigures\Symfony\Resource\Attribute as JsonApi;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'generated_records')]
#[JsonApi\JsonApiResource(type: 'generated-records', normalizationContext: ['groups' => ['record:read']], denormalizationContext: ['groups' => ['record:write']])]
#[JsonApi\FilterableFields([new JsonApi\FilterableField('id', ['eq', 'ne', 'neq', 'between', 'in', 'nin']), 'name', 'published-at'])]
#[JsonApi\SortableFields(['name', 'published-at'])]
class GeneratedRecord
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[JsonApi\Id]
    public ?int $id = null;

    #[ORM\Column(unique: true)]
    #[JsonApi\Attribute]
    #[\Symfony\Component\Serializer\Attribute\Groups(['record:read', 'record:write'])]
    public string $name;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[JsonApi\Attribute(name: 'published-at')]
    #[\Symfony\Component\Serializer\Attribute\Groups(['record:read', 'record:write'])]
    public ?\DateTimeImmutable $publishedAt = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    #[JsonApi\Relationship(targetType: 'generated-records', linkingPolicy: \AlexFigures\Symfony\Resource\Metadata\RelationshipLinkingPolicy::VERIFY)]
    public ?self $parent = null;
}
