<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Resource\Metadata;

use JsonApi\Symfony\Resource\Attribute\SerializationGroups;

final class AttributeMetadata
{
    public function __construct(
        public string $name,
        public ?string $propertyPath = null,
        public bool $readable = true,
        public bool $writable = true,
        /**
         * @var list<string>
         */
        public array $types = [],
        public bool $nullable = true,
        public ?SerializationGroups $serializationGroups = null,
    ) {
    }

    /**
     * Проверяет, доступен ли атрибут для чтения.
     */
    public function isReadable(): bool
    {
        if ($this->serializationGroups !== null) {
            return $this->serializationGroups->canRead();
        }

        return $this->readable;
    }

    /**
     * Проверяет, доступен ли атрибут для записи.
     */
    public function isWritable(bool $isCreate = false): bool
    {
        if ($this->serializationGroups !== null) {
            return $this->serializationGroups->canWrite($isCreate);
        }

        return $this->writable;
    }
}
