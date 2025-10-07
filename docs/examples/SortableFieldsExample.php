<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use JsonApi\Symfony\Resource\Attribute\Attribute;
use JsonApi\Symfony\Resource\Attribute\Id;
use JsonApi\Symfony\Resource\Attribute\JsonApiResource;
use JsonApi\Symfony\Resource\Attribute\Relationship;
use JsonApi\Symfony\Resource\Attribute\SerializationGroups;
use JsonApi\Symfony\Resource\Attribute\SortableFields;

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
    #[SerializationGroups(['read'])]
    public DateTimeImmutable $createdAt;

    #[Attribute]
    #[SerializationGroups(['read'])]
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
    #[SerializationGroups(['read'])]
    public DateTimeImmutable $createdAt;

    #[Attribute]
    #[SerializationGroups(['read'])]
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
    #[SerializationGroups(['read'])]
    public DateTimeImmutable $createdAt;

    #[Attribute]
    #[SerializationGroups(['read'])]
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

