<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Filter\Validation;

/**
 * Value object describing which fields/operators are allowed per resource type.
 */
final class FilterWhitelist
{
    /**
     * @param array<string, array<string, list<string>>> $whitelist
     */
    public function __construct(
        private readonly array $whitelist,
    ) {
    }

    /**
     * @return list<string>
     */
    public function allowedOperators(string $resourceType, string $fieldPath): array
    {
        return $this->whitelist[$resourceType][$fieldPath] ?? [];
    }
}
