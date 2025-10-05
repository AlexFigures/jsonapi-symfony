<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Resource\Metadata;

final class AttributeMetadata
{
    public function __construct(
        public string $name,
        public ?string $propertyPath = null,
        public bool $readable = true,
    ) {
    }
}
