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
    ) {
    }
}
