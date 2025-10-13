<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Resource\Attribute;

use Attribute;

/**
 * Declares a DTO class for a specific API version.
 *
 * This attribute is repeatable and should be applied to the JSON:API resource
 * class (typically an entity). Each instance maps an API version identifier
 * (e.g. "v1", "2025-01-01") to the DTO class that should be used to render
 * responses for that version.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class JsonApiDto
{
    /**
     * @param non-empty-string $version
     * @param class-string     $class
     */
    public function __construct(
        public readonly string $version,
        public readonly string $class,
    ) {
    }
}
