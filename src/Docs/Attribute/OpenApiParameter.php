<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Docs\Attribute;

use Attribute;

/**
 * Defines a parameter for an OpenAPI endpoint.
 *
 * @api This attribute is part of the public API and follows semantic versioning.
 * @since 1.0.0
 */
#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD)]
final class OpenApiParameter
{
    /**
     * @param string                    $name        Parameter name
     * @param string                    $in          Parameter location: 'query', 'path', 'header', 'cookie'
     * @param string                    $description Parameter description
     * @param bool                      $required    Whether the parameter is required
     * @param string                    $type        Parameter type: 'string', 'integer', 'boolean', 'array', 'object'
     * @param string|null               $format      Parameter format (e.g., 'date-time', 'email', 'uri')
     * @param array<string, mixed>|null $schema      Full schema definition (overrides type/format if provided)
     * @param mixed                     $example     Example value
     */
    public function __construct(
        public readonly string $name,
        public readonly string $in,
        public readonly string $description = '',
        public readonly bool $required = false,
        public readonly string $type = 'string',
        public readonly ?string $format = null,
        public readonly ?array $schema = null,
        public readonly mixed $example = null,
    ) {
        if (!in_array($in, ['query', 'path', 'header', 'cookie'], true)) {
            throw new \InvalidArgumentException(
                sprintf('Parameter location must be one of: query, path, header, cookie. Got: %s', $in)
            );
        }
    }
}
