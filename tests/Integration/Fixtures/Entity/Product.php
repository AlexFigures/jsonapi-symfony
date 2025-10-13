<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Fixtures\Entity;

use AlexFigures\Symfony\Resource\Attribute\Attribute;
use AlexFigures\Symfony\Resource\Attribute\Id;
use AlexFigures\Symfony\Resource\Attribute\JsonApiResource;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Sample entity with validation used to demonstrate the ValidatingDoctrinePersister.
 */
#[ORM\Entity]
#[ORM\Table(name: 'products')]
#[JsonApiResource(type: 'products')]
class Product
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    #[Id]
    #[Attribute]
    private string $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Attribute]
    #[Assert\NotBlank(message: 'Product name cannot be blank')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Product name must be at least {{ limit }} characters long',
        maxMessage: 'Product name cannot be longer than {{ limit }} characters'
    )]
    private string $name;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Attribute]
    #[Assert\NotBlank(message: 'Price cannot be blank')]
    #[Assert\Positive(message: 'Price must be positive')]
    #[Assert\LessThan(
        value: 1000000,
        message: 'Price cannot exceed {{ compared_value }}'
    )]
    private string $price;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Attribute]
    #[Assert\Email(message: 'The email "{{ value }}" is not a valid email')]
    private ?string $contactEmail = null;

    #[ORM\Column(type: 'integer')]
    #[Attribute]
    #[Assert\NotBlank(message: 'Stock cannot be blank')]
    #[Assert\PositiveOrZero(message: 'Stock cannot be negative')]
    private int $stock;

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

    public function getPrice(): string
    {
        return $this->price;
    }

    public function setPrice(string $price): self
    {
        $this->price = $price;
        return $this;
    }

    public function getContactEmail(): ?string
    {
        return $this->contactEmail;
    }

    public function setContactEmail(?string $contactEmail): self
    {
        $this->contactEmail = $contactEmail;
        return $this;
    }

    public function getStock(): int
    {
        return $this->stock;
    }

    public function setStock(int $stock): self
    {
        $this->stock = $stock;
        return $this;
    }
}
