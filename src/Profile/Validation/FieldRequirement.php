<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Profile\Validation;

/**
 * Describes a field requirement for a profile.
 *
 * Profiles can declare field requirements to ensure entities have the necessary
 * fields with correct types before the profile can be used.
 *
 * Example:
 * ```php
 * new FieldRequirement(
 *     type: \DateTimeImmutable::class,
 *     nullable: true,
 *     optional: false,
 *     description: 'Timestamp when entity was soft-deleted'
 * )
 * ```
 */
final class FieldRequirement
{
    /**
     * @param string $type        Expected field type (e.g., 'string', 'int', \DateTimeImmutable::class)
     * @param bool   $nullable    Whether the field can be null (default: false)
     * @param bool   $optional    Whether the field is optional (default: false)
     *                            If true, validation passes even if field doesn't exist
     * @param string $description Human-readable description of the field's purpose
     */
    public function __construct(
        public readonly string $type,
        public readonly bool $nullable = false,
        public readonly bool $optional = false,
        public readonly string $description = '',
    ) {
    }

    /**
     * Check if this field is required (not optional).
     */
    public function isRequired(): bool
    {
        return !$this->optional;
    }

    /**
     * Check if the given type matches this requirement.
     *
     * @param string $actualType The actual field type from Doctrine metadata
     */
    public function matchesType(string $actualType): bool
    {
        // Normalize types for comparison
        $expectedType = $this->normalizeType($this->type);
        $actualType = $this->normalizeType($actualType);

        return $expectedType === $actualType;
    }

    /**
     * Normalize type names for comparison.
     *
     * Handles common type aliases and FQCN normalization.
     */
    private function normalizeType(string $type): string
    {
        // Remove leading backslash from FQCN
        $type = ltrim($type, '\\');

        // Map common Doctrine types to PHP types
        $typeMap = [
            'datetime_immutable' => \DateTimeImmutable::class,
            'datetime' => \DateTime::class,
            'date_immutable' => \DateTimeImmutable::class,
            'date' => \DateTime::class,
            'integer' => 'int',
            'smallint' => 'int',
            'bigint' => 'int',
            'boolean' => 'bool',
            'text' => 'string',
        ];

        return $typeMap[$type] ?? $type;
    }
}
