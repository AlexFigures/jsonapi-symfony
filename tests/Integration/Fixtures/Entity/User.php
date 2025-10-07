<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Integration\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use JsonApi\Symfony\Resource\Attribute\Attribute;
use JsonApi\Symfony\Resource\Attribute\Id;
use JsonApi\Symfony\Resource\Attribute\JsonApiResource;
use JsonApi\Symfony\Resource\Attribute\SerializationGroups;

/**
 * Тестовая Entity для демонстрации групп сериализации.
 */
#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\HasLifecycleCallbacks]
#[JsonApiResource(type: 'users')]
class User
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    #[Id]
    #[Attribute]
    #[SerializationGroups(['read'])]
    private string $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Attribute]
    #[SerializationGroups(['read', 'write'])]
    private string $username;

    #[ORM\Column(type: 'string', length: 255)]
    #[Attribute]
    #[SerializationGroups(['read', 'write'])]
    private string $email;

    // Пароль: только для записи, никогда не возвращается в ответе
    #[ORM\Column(type: 'string', length: 255)]
    #[Attribute]
    #[SerializationGroups(['write'])]
    private string $password;

    // Slug: можно установить только при создании, потом read-only
    #[ORM\Column(type: 'string', length: 255, unique: true)]
    #[Attribute]
    #[SerializationGroups(['read', 'create'])]
    private string $slug;

    // CreatedAt: только для чтения, устанавливается автоматически
    #[ORM\Column(type: 'datetime_immutable')]
    #[Attribute]
    #[SerializationGroups(['read'])]
    private ?\DateTimeImmutable $createdAt = null;

    // UpdatedAt: только для чтения, обновляется автоматически
    #[ORM\Column(type: 'datetime_immutable')]
    #[Attribute]
    #[SerializationGroups(['read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    // Role: можно изменить только при обновлении (не при создании)
    #[ORM\Column(type: 'string', length: 50)]
    #[Attribute]
    #[SerializationGroups(['read', 'update'])]
    private string $role = 'user';

    public function __construct()
    {
    }

    #[ORM\PrePersist]
    public function initializeTimestamps(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTimeImmutable();
        }
        if ($this->updatedAt === null) {
            $this->updatedAt = new \DateTimeImmutable();
        }
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

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;
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

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;
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

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): self
    {
        $this->role = $role;
        return $this;
    }
}

