<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Filter\Operator;

use AlexFigures\JsonApi\Resource\Metadata\ResourceMetadata;

/**
 * Shared defaults for operator implementations.
 * @api
 */
abstract class AbstractOperator implements Operator
{
    public function supportsField(ResourceMetadata $meta, string $fieldPath): bool
    {
        // Field whitelists are validated separately by the query parser.
        return true;
    }

    /**
     * @return list<mixed>
     */
    public function normalizeValues(mixed $raw): array
    {
        return is_array($raw) ? array_values($raw) : [$raw];
    }
}
