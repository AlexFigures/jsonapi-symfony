<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Filter\Parser;

use JsonApi\Symfony\Filter\Ast\Node;

/**
 * Skeleton filter parser responsible for turning query parameters into an AST.
 *
 * The full Stage 5 implementation will introduce a proper grammar. For now
 * this class only stores the raw filter payload for later interpretation.
 */
final class FilterParser
{
    /**
     * @param array<string, mixed> $rawFilters
     */
    public function parse(array $rawFilters): ?Node
    {
        // The complete parsing logic will arrive in a later commit. Keeping the
        // method signature allows downstream code to type-hint against it.
        return null;
    }
}
