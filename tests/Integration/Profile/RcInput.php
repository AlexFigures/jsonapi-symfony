<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Integration\Profile;

use Symfony\Component\Validator\Constraints as Assert;

final class RcInput
{
    #[Assert\Length(min: 20)]
    public string $title = '';
    public string $content = 'Body';
}
