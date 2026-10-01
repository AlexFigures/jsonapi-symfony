<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Fixtures\Entity;

use AlexFigures\Symfony\Resource\Attribute as JsonApi;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'typed_identifier_records')]
#[JsonApi\JsonApiResource(type: 'typed-records', normalizationContext: ['groups' => ['typed:read']], denormalizationContext: ['groups' => ['typed:write']])]
class TypedIdentifierRecord
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[JsonApi\Id]
    public Uuid $id;

    #[ORM\Column]
    #[JsonApi\Attribute]
    #[\Symfony\Component\Serializer\Attribute\Groups(['typed:read', 'typed:write'])]
    public string $name;
}
