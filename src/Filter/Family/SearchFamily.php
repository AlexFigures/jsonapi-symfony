<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Filter\Family;

use JsonApi\Symfony\Filter\Ast\Node;

final class SearchFamily implements Family
{
    public function __construct(
        private readonly array $fields,
    ) {
    }

    public function build(array $raw): ?Node
    {
        // Placeholder implementation; proper full-text expansion to follow.
        return null;
    }
}
