<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Filter\Operator;

use AlexFigures\Symfony\Resource\Metadata\ResourceMetadata;

/**
 * Shared defaults for operator implementations.
 */
abstract class AbstractOperator implements Operator
{
    public function supportsField(ResourceMetadata $meta, string $fieldPath): bool
    {
        // Proper whitelist handling will arrive with the full implementation.
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
