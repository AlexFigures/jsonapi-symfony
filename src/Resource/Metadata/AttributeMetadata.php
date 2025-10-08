<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Resource\Metadata;

/**
 * Metadata for a JSON:API resource attribute.
 *
 * Serialization groups are now controlled via Symfony's #[Groups] attribute
 * on the entity properties, not through this metadata.
 */
final class AttributeMetadata
{
    public function __construct(
        public string $name,
        public ?string $propertyPath = null,
        /**
         * @var list<string>
         */
        public array $types = [],
        public bool $nullable = true,
    ) {
    }

    /**
     * Check if attribute is readable (for backward compatibility with tests).
     *
     * @deprecated This method is deprecated. Readability is now determined by
     * Symfony's #[Groups] attribute on entity properties and normalizationContext
     * in ResourceMetadata. This method always returns true for compatibility.
     */
    public function isReadable(): bool
    {
        return true;
    }

    /**
     * Check if attribute is writable (for backward compatibility with tests).
     *
     * @deprecated This method is deprecated. Writability is now determined by
     * Symfony's #[Groups] attribute on entity properties and denormalizationContext
     * in ResourceMetadata. This method always returns true for compatibility.
     */
    public function isWritable(bool $isCreate = true): bool
    {
        return true;
    }
}
