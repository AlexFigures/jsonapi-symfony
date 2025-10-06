<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Filter\Family;

use JsonApi\Symfony\Filter\Ast\Node;

interface Family
{
    /**
     * @param array<string, mixed> $raw
     */
    public function build(array $raw): ?Node;
}
