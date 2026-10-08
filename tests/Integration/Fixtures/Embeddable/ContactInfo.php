<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Integration\Fixtures\Embeddable;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Embeddable value object for contact information.
 * Used to test validation and denormalization of embedded objects.
 */
#[ORM\Embeddable]
class ContactInfo
{
    public function __construct(
        /**
         * Contact email address.
         */
        #[ORM\Column(type: 'string', length: 255, nullable: true)]
        #[Assert\Email(message: 'Invalid email format')]
        #[Assert\Length(max: 255)]
        #[Groups(['write', 'Default'])]
        private ?string $email = null,
        /**
         * Contact phone number.
         */
        #[ORM\Column(type: 'string', length: 50, nullable: true)]
        #[Assert\Length(min: 10, max: 50)]
        #[Groups(['write', 'Default'])]
        private ?string $phone = null
    ) {
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): void
    {
        $this->phone = $phone;
    }
}
