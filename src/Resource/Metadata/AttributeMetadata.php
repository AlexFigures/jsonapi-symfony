<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Resource\Metadata;

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
}
