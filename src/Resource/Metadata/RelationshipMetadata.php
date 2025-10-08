<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Resource\Metadata;

final class RelationshipMetadata
{
    public function __construct(
        public string $name,
        public bool $toMany = false,
        public ?string $targetType = null,
        public ?string $propertyPath = null,
        public ?string $targetClass = null,
        public bool $nullable = true,
        public RelationshipLinkingPolicy $linkingPolicy = RelationshipLinkingPolicy::REFERENCE,
        public RelationshipSemantics $semantics = RelationshipSemantics::MERGE,
        public ?int $minItems = null, // For to-many relationships
        public ?int $maxItems = null, // For to-many relationships
        public bool $writableOnCreate = true,
        public bool $writableOnUpdate = true,
    ) {
    }

    /**
     * Check if relationship is writable for the given operation.
     */
    public function isWritable(bool $isCreate): bool
    {
        return $isCreate ? $this->writableOnCreate : $this->writableOnUpdate;
    }
}
