<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Resource\Attribute;

use Attribute as PhpAttribute;

#[PhpAttribute(PhpAttribute::TARGET_PROPERTY | PhpAttribute::TARGET_METHOD)]
final class Attribute
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly bool $readable = true,
        public readonly bool $writable = true,
    ) {
    }
}
