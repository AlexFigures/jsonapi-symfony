<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Profile;

use Symfony\Component\Validator\Constraints as Assert;

final class RcInput
{
    #[Assert\Length(min: 20)]
    public string $title = '';
    public string $content = 'Body';
}
