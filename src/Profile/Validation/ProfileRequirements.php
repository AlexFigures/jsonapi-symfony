<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Profile\Validation;

/**
 * Describes the requirements a profile imposes on entities.
 *
 * Profiles can declare requirements to ensure entities have the necessary
 * attributes and fields before the profile can be used.
 *
 * Example:
 * ```php
 * new ProfileRequirements(
 *     attribute: SoftDeletable::class,
 *     fields: [
 *         'deletedAt' => new FieldRequirement(
 *             type: \DateTimeImmutable::class,
 *             nullable: true,
 *             description: 'Timestamp when entity was soft-deleted'
 *         ),
 *     ],
 *     description: 'Enables soft-delete semantics for resources'
 * )
 * ```
 */
final class ProfileRequirements
{
    /**
     * @param string|null                     $attribute   FQCN of required attribute (optional)
     * @param array<string, FieldRequirement> $fields      Map of field name => requirement
     * @param string                          $description Human-readable description of the profile
     */
    public function __construct(
        public readonly ?string $attribute = null,
        public readonly array $fields = [],
        public readonly string $description = '',
    ) {
    }

    /**
     * Check if this profile requires an attribute.
     */
    public function requiresAttribute(): bool
    {
        return $this->attribute !== null;
    }

    /**
     * Get the required attribute FQCN.
     *
     * @return string|null FQCN of the required attribute, or null if no attribute is required
     */
    public function getRequiredAttribute(): ?string
    {
        return $this->attribute;
    }

    /**
     * Get all field requirements.
     *
     * @return array<string, FieldRequirement>
     */
    public function getFieldRequirements(): array
    {
        return $this->fields;
    }

    /**
     * Get the description of the profile.
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Check if this profile has any field requirements.
     */
    public function hasFieldRequirements(): bool
    {
        return !empty($this->fields);
    }

    /**
     * Get only required (non-optional) field requirements.
     *
     * @return array<string, FieldRequirement>
     */
    public function getRequiredFields(): array
    {
        return array_filter(
            $this->fields,
            static fn (FieldRequirement $req) => $req->isRequired()
        );
    }

    /**
     * Get only optional field requirements.
     *
     * @return array<string, FieldRequirement>
     */
    public function getOptionalFields(): array
    {
        return array_filter(
            $this->fields,
            static fn (FieldRequirement $req) => !$req->isRequired()
        );
    }
}
