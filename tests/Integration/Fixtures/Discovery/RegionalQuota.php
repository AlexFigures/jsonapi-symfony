<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Fixtures\Discovery;

use AlexFigures\Symfony\Resource\Attribute as JsonApi;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[JsonApi\JsonApiResource(type: 'regional-quotas')]
final class RegionalQuota
{
    #[ORM\Id]
    #[ORM\Column]
    #[JsonApi\Id]
    public string $region;

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    public int $year;
}
