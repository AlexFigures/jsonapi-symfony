<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Filter\Family;

use AlexFigures\JsonApi\Filter\Ast\Node;

/** @internal */
final readonly class SearchFamily implements Family
{
    /**
     * @param list<string> $fields
     */
    public function __construct(
        private array $fields,
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
