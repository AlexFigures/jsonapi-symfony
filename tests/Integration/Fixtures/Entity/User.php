<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Integration\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use JsonApi\Symfony\Resource\Attribute\Attribute;
use JsonApi\Symfony\Resource\Attribute\Id;
use JsonApi\Symfony\Resource\Attribute\JsonApiResource;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Test Entity for demonstrating serialization groups.
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
    #[Groups(['user:read'])]
    private string $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Attribute]
    #[Groups(['user:read', 'user:write'])]
    private string $username;

    #[ORM\Column(type: 'string', length: 255)]
    #[Attribute]
    #[Groups(['user:read', 'user:write'])]
    private string $email;

    // Password: write-only, never returned in response
    #[ORM\Column(type: 'string', length: 255)]
    #[Attribute]
    #[Groups(['user:write'])]
    private string $password;

    // Slug: can only be set during creation, then read-only
    #[ORM\Column(type: 'string', length: 255, unique: true)]
    #[Attribute]
    #[Groups(['user:read', 'user:create'])]
    private string $slug;

    // CreatedAt: read-only, set automatically
    #[ORM\Column(type: 'datetime_immutable')]
    #[Attribute]
    #[Groups(['user:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    // UpdatedAt: read-only, updated automatically
    #[ORM\Column(type: 'datetime_immutable')]
    #[Attribute]
    #[Groups(['user:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    // Role: can only be changed during update (not during creation)
    #[ORM\Column(type: 'string', length: 50)]
    #[Attribute]
    #[Groups(['user:read', 'user:update'])]
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

