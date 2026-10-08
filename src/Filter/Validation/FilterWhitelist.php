<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Filter\Validation;

/**
 * Value object describing which fields/operators are allowed per resource type.
 * @internal
 */
final readonly class FilterWhitelist
{
    /**
     * @param array<string, array<string, list<string>>> $whitelist
     */
    public function __construct(
        private array $whitelist,
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
