<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Fixtures\Entity;

use AlexFigures\Symfony\Resource\Attribute\Attribute;
use AlexFigures\Symfony\Resource\Attribute\Id;
use AlexFigures\Symfony\Resource\Attribute\JsonApiResource;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

/**
 * Test entity for verifying support of various PHP/Symfony types.
 *
 * This entity tests denormalization of:
 * - Uuid (via UidNormalizer)
 * - BackedEnum (via BackedEnumNormalizer)
 * - DateTimeImmutable (via DateTimeNormalizer)
 * - DateTimeZone (via DateTimeZoneNormalizer)
 * - DateInterval (via DateIntervalNormalizer)
 */
#[ORM\Entity]
#[ORM\Table(name: 'type_test_entities')]
#[JsonApiResource(
    type: 'type-test-entities',
    normalizationContext: ['groups' => ['type_test:read']],
    denormalizationContext: ['groups' => ['type_test:write']],
)]
class TypeTestEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    #[Id]
    #[Attribute]
    #[Groups(['type_test:read'])]
    private string $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Attribute]
    #[Groups(['type_test:read', 'type_test:write'])]
    private string $name;

    /**
     * Test Uuid type (UidNormalizer).
     * Uses custom Doctrine type 'uuid' for database persistence.
     */
    #[ORM\Column(type: 'uuid', nullable: true)]
    #[Attribute]
    #[Groups(['type_test:read', 'type_test:write'])]
    private ?Uuid $uuid = null;

    /**
     * Test BackedEnum type (BackedEnumNormalizer).
     */
    #[ORM\Column(enumType: ArticleStatus::class, nullable: true)]
    #[Attribute]
    #[Groups(['type_test:read', 'type_test:write'])]
    private ?ArticleStatus $status = null;

    /**
     * Test DateTimeImmutable type (DateTimeNormalizer).
     */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Attribute]
    #[Groups(['type_test:read', 'type_test:write'])]
    private ?DateTimeImmutable $publishedAt = null;

    /**
     * Test DateTimeZone type (DateTimeZoneNormalizer).
     * Uses custom Doctrine type 'datetimezone' for database persistence.
     */
    #[ORM\Column(type: 'datetimezone', nullable: true)]
    #[Attribute]
    #[Groups(['type_test:read', 'type_test:write'])]
    private ?DateTimeZone $timezone = null;

    /**
     * Test DateInterval type (DateIntervalNormalizer).
     * Uses custom Doctrine type 'dateinterval' for database persistence.
     */
    #[ORM\Column(type: 'dateinterval', nullable: true)]
    #[Attribute]
    #[Groups(['type_test:read', 'type_test:write'])]
    private ?DateInterval $duration = null;

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

    public function getUuid(): ?Uuid
    {
        return $this->uuid;
    }

    public function setUuid(?Uuid $uuid): self
    {
        $this->uuid = $uuid;
        return $this;
    }

    public function getStatus(): ?ArticleStatus
    {
        return $this->status;
    }

    public function setStatus(?ArticleStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getPublishedAt(): ?DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?DateTimeImmutable $publishedAt): self
    {
        $this->publishedAt = $publishedAt;
        return $this;
    }

    public function getTimezone(): ?DateTimeZone
    {
        return $this->timezone;
    }

    public function setTimezone(?DateTimeZone $timezone): self
    {
        $this->timezone = $timezone;
        return $this;
    }

    public function getDuration(): ?DateInterval
    {
        return $this->duration;
    }

    public function setDuration(?DateInterval $duration): self
    {
        $this->duration = $duration;
        return $this;
    }
}

