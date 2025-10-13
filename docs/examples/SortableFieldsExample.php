<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use AlexFigures\Symfony\Resource\Attribute\Attribute;
use AlexFigures\Symfony\Resource\Attribute\Id;
use AlexFigures\Symfony\Resource\Attribute\JsonApiResource;
use AlexFigures\Symfony\Resource\Attribute\Relationship;
use AlexFigures\Symfony\Resource\Attribute\SortableFields;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Example entity demonstrating the use of SortableFields attribute.
 *
 * The SortableFields attribute defines which fields can be used in the
 * `sort` query parameter of JSON:API requests.
 */
#[JsonApiResource(type: 'categories')]
#[SortableFields(['name', 'slug', 'sortOrder', 'createdAt', 'updatedAt', 'depth'])]
class Category
{
    #[Id]
    #[Attribute]
    public string $id;

    #[Attribute]
    public string $name;

    #[Attribute]
    public string $slug;

    #[Attribute]
    public int $sortOrder;

    #[Attribute]
    #[Groups(['category:read'])]
    public DateTimeImmutable $createdAt;

    #[Attribute]
    #[Groups(['category:read'])]
    public ?DateTimeImmutable $updatedAt = null;

    #[Attribute]
    public int $depth;

    #[Relationship(targetType: 'categories')]
    public ?Category $parent = null;
}

/**
 * Example with minimal sortable fields.
 */
#[JsonApiResource(type: 'brands')]
#[SortableFields(['name', 'isActive', 'createdAt', 'updatedAt'])]
class Brand
{
    #[Id]
    #[Attribute]
    public string $id;

    #[Attribute]
    public string $name;

    #[Attribute]
    public bool $isActive;

    #[Attribute]
    #[Groups(['tag:read'])]
    public DateTimeImmutable $createdAt;

    #[Attribute]
    #[Groups(['tag:read'])]
    public ?DateTimeImmutable $updatedAt = null;
}

/**
 * Example with many sortable fields.
 */
#[JsonApiResource(type: 'manufacturers')]
#[SortableFields(['name', 'isActive', 'year', 'legalEntity', 'createdAt', 'updatedAt'])]
class Manufacturer
{
    #[Id]
    #[Attribute]
    public string $id;

    #[Attribute]
    public string $name;

    #[Attribute]
    public bool $isActive;

    #[Attribute]
    public ?int $year = null;

    #[Attribute]
    public ?string $legalEntity = null;

    #[Attribute]
    #[Groups(['company:read'])]
    public DateTimeImmutable $createdAt;

    #[Attribute]
    #[Groups(['company:read'])]
    public ?DateTimeImmutable $updatedAt = null;
}

/**
 * Example without SortableFields attribute.
 * In this case, no fields will be sortable (unless configured via YAML).
 */
#[JsonApiResource(type: 'tags')]
class Tag
{
    #[Id]
    #[Attribute]
    public string $id;

    #[Attribute]
    public string $name;
}

