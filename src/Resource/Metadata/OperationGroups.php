<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Resource\Metadata;

/**
 * Manages validation and serialization groups for different operations.
 *
 * Follows API Platform patterns for operation-specific group handling.
 */
final class OperationGroups
{
    /**
     * @param list<string> $validationGroupsCreate
     * @param list<string> $validationGroupsUpdate
     * @param list<string> $serializationGroupsCreate
     * @param list<string> $serializationGroupsUpdate
     */
    public function __construct(
        public readonly array $validationGroupsCreate = ['create', 'Default'],
        public readonly array $validationGroupsUpdate = ['update', 'Default'],
        public readonly array $serializationGroupsCreate = ['write', 'create', 'Default'],
        public readonly array $serializationGroupsUpdate = ['write', 'update', 'Default'],
    ) {
    }

    /**
     * Returns validation groups for the given operation.
     *
     * @return list<string>
     */
    public function getValidationGroups(bool $isCreate): array
    {
        return $isCreate ? $this->validationGroupsCreate : $this->validationGroupsUpdate;
    }

    /**
     * Returns serialization groups for the given operation.
     *
     * @return list<string>
     */
    public function getSerializationGroups(bool $isCreate): array
    {
        return $isCreate ? $this->serializationGroupsCreate : $this->serializationGroupsUpdate;
    }

    /**
     * Creates default operation groups.
     */
    public static function default(): self
    {
        return new self();
    }

    /**
     * Creates operation groups with custom validation groups.
     *
     * @param list<string> $createGroups
     * @param list<string> $updateGroups
     */
    public static function withValidationGroups(array $createGroups, array $updateGroups): self
    {
        return new self(
            validationGroupsCreate: $createGroups,
            validationGroupsUpdate: $updateGroups,
        );
    }

    /**
     * Creates operation groups with custom serialization groups.
     *
     * @param list<string> $createGroups
     * @param list<string> $updateGroups
     */
    public static function withSerializationGroups(array $createGroups, array $updateGroups): self
    {
        return new self(
            serializationGroupsCreate: $createGroups,
            serializationGroupsUpdate: $updateGroups,
        );
    }
}
