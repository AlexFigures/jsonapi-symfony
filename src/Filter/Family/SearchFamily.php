<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Filter\Family;

use JsonApi\Symfony\Filter\Ast\Node;

final class SearchFamily implements Family
{
    /**
     * @param list<string> $fields
     */
    public function __construct(
        private readonly array $fields,
    ) {
    }

    public function build(array $raw): ?Node
    {
        if ($this->fields === []) {
            return null;
        }

        // Placeholder implementation; proper full-text expansion to follow.
        return null;
    }
}
