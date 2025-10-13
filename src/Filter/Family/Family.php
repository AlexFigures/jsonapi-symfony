<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Filter\Family;

use AlexFigures\Symfony\Filter\Ast\Node;

interface Family
{
    /**
     * @param array<string, mixed> $raw
     */
    public function build(array $raw): ?Node;
}
