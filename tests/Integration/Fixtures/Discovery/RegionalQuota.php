<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Integration\Fixtures\Discovery;

use AlexFigures\JsonApi\Resource\Attribute as JsonApi;
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
