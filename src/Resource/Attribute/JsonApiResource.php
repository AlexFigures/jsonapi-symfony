<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Resource\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class JsonApiResource
{
    public function __construct(
        public readonly string $type,
        public readonly ?string $routePrefix = null,
        public readonly ?string $description = null,
        public readonly bool $exposeId = true,
    ) {
    }
}
