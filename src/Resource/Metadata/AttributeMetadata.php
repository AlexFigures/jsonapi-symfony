<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Resource\Metadata;

use JsonApi\Symfony\Resource\Attribute\SerializationGroups;

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
        public ?SerializationGroups $serializationGroups = null,
    ) {
    }

    /**
     * Checks if the attribute is available for reading.
     *
     * By default (if SerializationGroups is not specified) the attribute is available for reading.
     */
    public function isReadable(): bool
    {
        if ($this->serializationGroups !== null) {
            return $this->serializationGroups->canRead();
        }

        // Default behavior: attribute is available for reading
        return true;
    }

    /**
     * Checks if the attribute is available for writing.
     *
     * By default (if SerializationGroups is not specified) the attribute is available for writing.
     */
    public function isWritable(bool $isCreate = false): bool
    {
        if ($this->serializationGroups !== null) {
            return $this->serializationGroups->canWrite($isCreate);
        }

        // Default behavior: attribute is available for writing
        return true;
    }
}
