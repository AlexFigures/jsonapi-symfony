<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Filter\Family;

use AlexFigures\JsonApi\Filter\Ast\Node;

/** @internal */
interface Family
{
    /**
     * @param array<string, mixed> $raw
     */
    public function build(array $raw): ?Node;
}
